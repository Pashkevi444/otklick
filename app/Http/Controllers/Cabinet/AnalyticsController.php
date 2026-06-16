<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Enums\LeadAnalyticsPeriod;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Repositories\Contracts\LeadAnalyticsRepositoryInterface;
use App\Services\LeadAnalyticsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Выгрузки аналитики по лидам в CSV (UTF-8 с BOM — корректно открывается в Excel).
 * Данные скоупятся текущим тенантом (RLS + scope).
 */
final class AnalyticsController extends Controller
{
    public function __construct(
        private readonly LeadAnalyticsRepositoryInterface $repository,
        private readonly LeadAnalyticsService $analytics,
    ) {}

    public function export(Request $request, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['leads', 'daily'], true), 404);

        $period = LeadAnalyticsPeriod::fromValue($request->query('period'));

        return $type === 'leads'
            ? $this->exportLeads($period)
            : $this->exportDaily($period);
    }

    private function exportLeads(LeadAnalyticsPeriod $period): StreamedResponse
    {
        [$from, $to] = $period->range();
        $leads = $this->repository->leadsForAnalytics($from, $to);

        return $this->stream("leads-{$period->value}", [
            'Дата', 'Канал', 'Имя', 'Телефон', 'Аккаунт/IP', 'Статус', 'Запись', 'Сообщений (вход.)', 'Уточнений',
        ], $leads->map(fn (Conversation $c): array => [
            $c->created_at?->format('Y-m-d H:i') ?? '',
            $c->channel?->type?->label() ?? '—',
            (string) $c->contact_name,
            (string) $c->contact_phone,
            (string) $c->contact_ref,
            $c->status->label(),
            $c->booked_at !== null ? 'да' : 'нет',
            (string) (int) $c->getAttribute('inbound_count'),
            (string) (int) $c->clarification_attempts,
        ])->all());
    }

    private function exportDaily(LeadAnalyticsPeriod $period): StreamedResponse
    {
        $daily = $this->analytics->forPeriod($period)->daily;

        return $this->stream("leads-by-day-{$period->value}", ['Дата', 'Лидов'],
            array_map(fn (array $row): array => [$row['date'], (string) $row['value']], $daily));
    }

    /**
     * @param  list<string>  $header
     * @param  list<list<string>>  $rows
     */
    private function stream(string $name, array $header, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF"); // BOM для Excel
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $name.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
