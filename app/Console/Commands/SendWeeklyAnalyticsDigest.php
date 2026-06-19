<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\DTO\Analytics\AnalyticsRange;
use App\DTO\Analytics\BreakdownSlice;
use App\DTO\Analytics\LeadAnalytics;
use App\DTO\Analytics\MetricCard;
use App\DTO\OwnerNotification;
use App\Enums\LeadAnalyticsPeriod;
use App\Models\NotificationRecipient;
use App\Models\Tenant;
use App\Services\LeadAnalyticsService;
use App\Services\LeadInsightsService;
use App\Services\NotificationService;
use App\Tenancy\TenantInitializer;
use Illuminate\Console\Command;

/**
 * Недельный AI-дайджест владельцу («директор»): метрики за 7 дней + разбор «что
 * улучшить» (тот же {@see LeadInsightsService}, что в аналитике) проактивно
 * уходят в Telegram/почту через систему уведомлений ({@see NotificationService}).
 *
 * Только тенанты с правом `aiInsights` (как сам блок в аналитике) и с лидами за
 * неделю — пустым дайджестом владельца не спамим. Запускается планировщиком (раз
 * в неделю); у остальных ничего не крутится.
 */
final class SendWeeklyAnalyticsDigest extends Command
{
    protected $signature = 'analytics:weekly-digest';

    protected $description = 'Шлёт владельцам недельный AI-дайджест по лидам (право aiInsights).';

    public function handle(
        TenantInitializer $tenancy,
        LeadAnalyticsService $analytics,
        LeadInsightsService $insights,
        NotificationService $notifications,
    ): int {
        $sent = 0;

        Tenant::query()->pluck('id')->each(function (string $tid) use ($tenancy, $analytics, $insights, $notifications, &$sent): void {
            $sent += $tenancy->run($tid, function () use ($tid, $analytics, $insights, $notifications): int {
                $tenant = Tenant::query()->find($tid);

                if ($tenant === null || ! $tenant->hasActiveAccess() || ! $tenant->features()->has('aiInsights')) {
                    return 0;
                }

                // Бизнес мог выключить дайджест тумблером в «Уведомлениях».
                if (($tenant->settings['weekly_digest'] ?? true) !== true) {
                    return 0;
                }

                $range = AnalyticsRange::fromPeriod(LeadAnalyticsPeriod::Week);
                $data = $analytics->forPeriod($range);

                // Нет лидов за неделю — не отправляем пустой дайджест.
                if (array_sum(array_map(static fn (array $d): int => (int) $d['value'], $data->daily)) === 0) {
                    return 0;
                }

                $payload = $insights->refresh($range);
                /** @var list<array<string, mixed>> $items */
                $items = is_array($payload) && is_array($payload['items'] ?? null) ? $payload['items'] : [];

                // Недельный дайджест — стратегический отчёт, шлём только директорам.
                $notifications->dispatchToOwners(
                    $tenant,
                    $this->compose($tenant, $data, $items),
                    static fn (NotificationRecipient $r): bool => $r->isDirector(),
                );

                return 1;
            });
        });

        $this->info("Недельных дайджестов отправлено: {$sent}.");

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $insights
     */
    private function compose(Tenant $tenant, LeadAnalytics $data, array $insights): OwnerNotification
    {
        $lines = ["Итоги за {$data->period['label']} ({$data->period['from']} — {$data->period['to']}):", ''];

        foreach ($data->kpis as $kpi) {
            /** @var MetricCard $kpi */
            $delta = $kpi->deltaPct !== null
                ? ' ('.($kpi->deltaPct >= 0 ? '↑' : '↓').abs((int) round($kpi->deltaPct)).'%)'
                : '';
            $lines[] = "• {$kpi->label}: {$kpi->value}{$kpi->unit}{$delta}";
        }

        $channels = array_map(static fn (BreakdownSlice $s): string => "{$s->label} — {$s->value}", $data->byChannel);
        if ($channels !== []) {
            $lines[] = '';
            $lines[] = 'Каналы: '.implode(', ', $channels);
        }

        $top = array_slice($insights, 0, 3);
        if ($top !== []) {
            $lines[] = '';
            $lines[] = 'Что улучшить:';
            $n = 1;
            foreach ($top as $item) {
                $title = trim((string) ($item['title'] ?? ''));
                $action = trim((string) ($item['action'] ?? ''));
                $lines[] = $n.'. '.$title.($action !== '' ? ' — '.$action : '');
                $n++;
            }
        }

        $lines[] = '';
        $lines[] = 'Подробнее: '.$this->analyticsLink();

        return new OwnerNotification("«{$tenant->name}»: итоги недели", implode("\n", $lines));
    }

    private function analyticsLink(): string
    {
        $domain = (string) config('app.business_domain');
        $base = $domain !== '' ? "https://{$domain}" : rtrim((string) config('app.url'), '/');

        return "{$base}/cabinet/analytics?period=7d";
    }
}
