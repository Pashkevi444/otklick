<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Enums\LeadAnalyticsPeriod;
use App\Http\Controllers\Controller;
use App\Jobs\RefreshLeadInsights;
use App\Models\Conversation;
use App\Repositories\Contracts\LeadAnalyticsRepositoryInterface;
use App\Services\LeadAnalyticsService;
use App\Services\LeadInsightsService;
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
    ) {}

    public function index(Request $request): Response
    {
        $period = LeadAnalyticsPeriod::fromValue($request->query('period'));
        $insights = $this->insights->cached($period);

        // ИИ-разбор устарел/не считался — обновим в фоне (Horizon), без задержки
        // ответа. Лок на 5 минут, чтобы не плодить задачи при перезагрузках.
        $tenantId = $request->user()?->tenant_id;
        if ($tenantId !== null && ($insights === null || $this->insights->isStale($insights))
            && Cache::add("lead-insights-refreshing:{$tenantId}:{$period->value}", true, 300)) {
            RefreshLeadInsights::dispatch($tenantId, $period->value);
        }

        return Inertia::render('Cabinet/Analytics', [
            'analytics' => $this->analytics->forPeriod($period)->toArray(),
            'insights' => $insights,
        ]);
    }

    /**
     * Ручное обновление ИИ-разбора по кнопке (синхронно — пользователь ждёт ответ).
     */
    public function refreshInsights(Request $request): RedirectResponse
    {
        $period = LeadAnalyticsPeriod::fromValue($request->input('period'));
        $this->insights->refresh($period);

        return back();
    }

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
