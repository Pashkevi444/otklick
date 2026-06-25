<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\DTO\AnalyticsRange;
use App\Modules\Analytics\DTO\MetricCard;
use App\Modules\Analytics\DTO\ServiceRevenue;
use App\Modules\Analytics\DTO\ValueReport;
use App\Modules\Analytics\Repositories\Contracts\LeadAnalyticsRepositoryInterface;
use App\Modules\Booking\Models\CrmConnection;
use App\Modules\Booking\Repositories\Contracts\CrmConnectionRepositoryInterface;
use App\Modules\Conversations\Models\Conversation;
use Illuminate\Support\Collection;

/**
 * «Отчёт ценности»: что бот принёс бизнесу в деньгах и записях — отдельно по
 * каждой CRM тенанта. Выручка считается точно по записям (снимок цены услуги в
 * момент записи, см. {@see BookingFlow}). Метрики и динамика к
 * прошлому периоду переиспользуют формат дашборда ({@see MetricCard}).
 */
final readonly class ValueReportService
{
    /** Сколько услуг показывать в «топе по выручке». */
    private const int TOP_SERVICES = 5;

    public function __construct(
        private LeadAnalyticsRepositoryInterface $repository,
        private CrmConnectionRepositoryInterface $connections,
    ) {}

    /**
     * Отчёты по всем CRM-подключениям тенанта за период. Пустой массив — CRM нет.
     *
     * @return list<ValueReport>
     */
    public function reportsForPeriod(AnalyticsRange $range): array
    {
        return $this->connections->forCurrentTenant()
            ->map(fn (CrmConnection $connection): ValueReport => $this->reportFor($connection, $range))
            ->values()
            ->all();
    }

    private function reportFor(CrmConnection $connection, AnalyticsRange $range): ValueReport
    {
        $id = (string) $connection->id;
        $hasPrevious = $range->previousFrom !== null && $range->previousTo !== null;

        $bookings = $this->repository->bookingsForCrm($id, $range->from, $range->to);
        $now = $this->aggregate($bookings);
        $prev = $hasPrevious
            ? $this->aggregate($this->repository->bookingsForCrm($id, $range->previousFrom, $range->previousTo))
            : null;

        $cancelled = $this->repository->cancelledCountForCrm($id, $range->from, $range->to);
        $prevCancelled = $hasPrevious
            ? $this->repository->cancelledCountForCrm($id, $range->previousFrom, $range->previousTo)
            : null;

        $leads = $this->repository->leadsCount($range->from, $range->to);
        $conversion = $leads > 0 ? round($now['bookings'] / $leads * 100, 1) : 0.0;
        $prevLeads = $hasPrevious ? $this->repository->leadsCount($range->previousFrom, $range->previousTo) : 0;
        $prevConversion = $prev !== null && $prevLeads > 0 ? round($prev['bookings'] / $prevLeads * 100, 1) : null;

        $kpis = [
            new MetricCard('revenue', 'Выручка (оформлено ботом)', $now['revenue'], ' ₽',
                $this->delta($now['revenue'], $prev['revenue'] ?? null), true,
                'Сумма цен услуг по записям этой CRM за период.'),
            new MetricCard('bookings', 'Записей оформлено', $now['bookings'], '',
                $this->delta($now['bookings'], $prev['bookings'] ?? null), true,
                'Сколько записей бот создал в этой CRM.'),
            new MetricCard('avg_check', 'Средний чек', $now['avgCheck'], ' ₽',
                $this->delta($now['avgCheck'], $prev['avgCheck'] ?? null), true,
                'Выручка ÷ число записей с указанной ценой.'),
            new MetricCard('conversion', 'Конверсия лид→запись', $conversion, '%',
                $this->delta($conversion, $prevConversion), true,
                'Доля лидов периода, которых бот довёл до записи в этой CRM.'),
            new MetricCard('reminders', 'Напоминаний клиентам', $now['reminders'], '',
                $this->delta($now['reminders'], $prev['reminders'] ?? null), true,
                'Напоминания о визите — меньше неявок.'),
            new MetricCard('cancelled', 'Отмен после записи', $cancelled, '',
                $this->delta($cancelled, $prevCancelled), false,
                'Клиент отменил оформленную запись (упущенная выручка).'),
        ];

        $note = $now['withoutPrice'] > 0
            ? "У {$now['withoutPrice']} из {$now['bookings']} записей цена не указана в CRM — они не вошли в выручку."
            : null;

        return new ValueReport($id, $this->label($connection), $kpis, $this->topServices($bookings), $note);
    }

    /**
     * Денежные агрегаты по набору записей.
     *
     * @param  Collection<int, Conversation>  $bookings
     * @return array{revenue: int, bookings: int, avgCheck: int, reminders: int, withoutPrice: int}
     */
    private function aggregate(Collection $bookings): array
    {
        $revenue = 0;
        $reminders = 0;
        $withoutPrice = 0;

        foreach ($bookings as $conversation) {
            $price = $conversation->booked_service_price;

            if ($price === null) {
                $withoutPrice++;
            } else {
                $revenue += $price;
            }

            $reminders += is_array($conversation->reminders_sent) ? count($conversation->reminders_sent) : 0;
        }

        $count = $bookings->count();
        $pricedCount = $count - $withoutPrice;

        return [
            'revenue' => $revenue,
            'bookings' => $count,
            'avgCheck' => $pricedCount > 0 ? (int) round($revenue / $pricedCount) : 0,
            'reminders' => $reminders,
            'withoutPrice' => $withoutPrice,
        ];
    }

    /**
     * Топ услуг по выручке за период.
     *
     * @param  Collection<int, Conversation>  $bookings
     * @return list<ServiceRevenue>
     */
    private function topServices(Collection $bookings): array
    {
        /** @var array<string, array{bookings: int, revenue: int}> $byTitle */
        $byTitle = [];

        foreach ($bookings as $conversation) {
            $title = $conversation->booked_service_title ?? 'Без названия';
            $byTitle[$title] ??= ['bookings' => 0, 'revenue' => 0];
            $byTitle[$title]['bookings']++;
            $byTitle[$title]['revenue'] += $conversation->booked_service_price ?? 0;
        }

        $rows = [];
        foreach ($byTitle as $title => $agg) {
            $rows[] = new ServiceRevenue($title, $agg['bookings'], $agg['revenue']);
        }

        usort($rows, static fn (ServiceRevenue $a, ServiceRevenue $b): int => $b->revenue <=> $a->revenue);

        return array_slice($rows, 0, self::TOP_SERVICES);
    }

    private function label(CrmConnection $connection): string
    {
        $label = $connection->provider->label();
        $company = $connection->credential('company_id');

        return $company !== null && $company !== '' ? "{$label} · #{$company}" : $label;
    }

    private function delta(int|float $current, int|float|null $previous): ?float
    {
        if ($previous === null || (float) $previous <= 0.0) {
            return null;
        }

        return round(((float) $current - (float) $previous) / (float) $previous * 100, 1);
    }
}
