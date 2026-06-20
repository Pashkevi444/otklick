<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\DTO\Analytics\AnalyticsRange;
use App\DTO\Analytics\ValueReport;
use App\Http\Controllers\Controller;
use App\Jobs\RefreshLeadInsights;
use App\Models\Conversation;
use App\Repositories\Contracts\LeadAnalyticsRepositoryInterface;
use App\Services\LeadAnalyticsService;
use App\Services\LeadInsightsService;
use App\Services\ValueReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Аналитика по лидам: отдельная страница кабинета + выгрузки в CSV (UTF-8 с BOM —
 * корректно открывается в Excel). Данные скоупятся текущим тенантом (RLS + scope).
 */
final class AnalyticsController extends Controller
{
    public function __construct(
        private readonly LeadAnalyticsRepositoryInterface $repository,
        private readonly LeadAnalyticsService $analytics,
        private readonly LeadInsightsService $insights,
        private readonly ValueReportService $valueReports,
    ) {}

    public function index(Request $request): Response
    {
        $range = AnalyticsRange::resolve($request->query('period'), $request->query('from'), $request->query('to'));

        // ИИ-рекомендации — премиум-возможность (Макс/Индивидуальный). Без права
        // не считаем и не показываем: остаётся общая аналитика (графики/KPI).
        $aiInsights = $this->canUseAiInsights($request);
        $insights = null;

        if ($aiInsights) {
            $insights = $this->insights->cached($range);

            // ИИ-разбор устарел/не считался — обновим в фоне (Horizon), без задержки
            // ответа. Лок на 5 минут, чтобы не плодить задачи при перезагрузках.
            $tenantId = $request->user()?->tenant_id;
            if ($tenantId !== null && ($insights === null || $this->insights->isStale($insights))
                && Cache::add("lead-insights-refreshing:{$tenantId}:{$range->cacheKey()}", true, 300)) {
                RefreshLeadInsights::dispatch($tenantId, $range->key, $range->from->toDateString(), $range->to->toDateString());
            }
        }

        return Inertia::render('Cabinet/Analytics', [
            'analytics' => $this->analytics->forPeriod($range)->toArray(),
            'insights' => $insights,
            'aiInsights' => $aiInsights,
            // «Отчёт ценности» — деньги/записи, оформленные ботом, отдельно по
            // каждой CRM. Показываем только если тарифом разрешена интеграция с CRM
            // (нет права на CRM → нет и анализа по ней, остаётся общая аналитика).
            'valueReports' => $this->canUseCrm($request)
                ? array_map(static fn (ValueReport $r): array => $r->toArray(), $this->valueReports->reportsForPeriod($range))
                : [],
        ]);
    }

    /**
     * Ручное обновление ИИ-разбора по кнопке (синхронно — пользователь ждёт ответ).
     */
    public function refreshInsights(Request $request): RedirectResponse
    {
        // Ручной пересчёт ИИ-разбора — тоже только при праве на ИИ-рекомендации.
        abort_unless($this->canUseAiInsights($request), 403);

        $range = AnalyticsRange::resolve($request->input('period'), $request->input('from'), $request->input('to'));
        $this->insights->refresh($range);

        return back();
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['leads', 'daily', 'value'], true), 404);

        $range = AnalyticsRange::resolve($request->query('period'), $request->query('from'), $request->query('to'));

        if ($type === 'value') {
            // Выгрузка по CRM доступна только при праве на интеграцию с CRM.
            abort_unless($this->canUseCrm($request), 403);

            return $this->exportValue($range, (string) $request->query('crm', ''));
        }

        return $type === 'leads' ? $this->exportLeads($range) : $this->exportDaily($range);
    }

    /** Разрешена ли тенанту интеграция с CRM (право `crm` в тарифе/оверрайдах). */
    private function canUseCrm(Request $request): bool
    {
        $tenant = $request->user()?->tenant;

        return $tenant !== null && $tenant->features()->has('crm');
    }

    /** Доступны ли тенанту ИИ-рекомендации в аналитике (право `aiInsights`). */
    private function canUseAiInsights(Request $request): bool
    {
        $tenant = $request->user()?->tenant;

        return $tenant !== null && $tenant->features()->has('aiInsights');
    }

    /**
     * Выгрузка оформленных ботом записей конкретной CRM (для «Отчёта ценности»):
     * услуга, цена-снимок, контакты. Скоупится тенантом (RLS) + фильтром по CRM.
     */
    private function exportValue(AnalyticsRange $range, string $crmConnectionId): StreamedResponse
    {
        abort_if($crmConnectionId === '', 404);

        $bookings = $this->repository->bookingsForCrm($crmConnectionId, $range->from, $range->to);

        return $this->stream("value-{$crmConnectionId}-{$range->key}", [
            'Дата записи', 'Услуга', 'Цена, ₽', 'Имя', 'Телефон', 'Канал', 'Напоминаний',
        ], $bookings->map(fn (Conversation $c): array => [
            $c->booked_at?->format('Y-m-d H:i') ?? '',
            (string) $c->booked_service_title,
            $c->booked_service_price !== null ? (string) $c->booked_service_price : '',
            (string) $c->displayName(),
            (string) $c->displayPhone(),
            $c->channel?->type?->label() ?? '—',
            (string) count($c->reminders_sent ?? []),
        ])->all());
    }

    private function exportLeads(AnalyticsRange $range): StreamedResponse
    {
        $leads = $this->repository->leadsForAnalytics($range->from, $range->to);

        return $this->stream("leads-{$range->key}", [
            'Дата', 'Канал', 'Имя', 'Телефон', 'Аккаунт/IP', 'Статус', 'Запись', 'Сообщений (вход.)', 'Уточнений',
        ], $leads->map(fn (Conversation $c): array => [
            $c->created_at?->format('Y-m-d H:i') ?? '',
            $c->channel?->type?->label() ?? '—',
            (string) $c->displayName(),
            (string) $c->displayPhone(),
            (string) $c->contact_ref,
            $c->status->label(),
            $c->booked_at !== null ? 'да' : 'нет',
            (string) (int) $c->getAttribute('inbound_count'),
            (string) (int) $c->clarification_attempts,
        ])->all());
    }

    private function exportDaily(AnalyticsRange $range): StreamedResponse
    {
        $daily = $this->analytics->forPeriod($range)->daily;

        return $this->stream("leads-by-day-{$range->key}", ['Дата', 'Лидов'],
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
