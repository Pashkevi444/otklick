<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Jobs;

use App\Modules\Analytics\DTO\AnalyticsRange;
use App\Modules\Analytics\Services\LeadInsightsService;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Пересчёт ИИ-разбора лидов в фоне (Horizon), чтобы не звать LLM в HTTP-запросе.
 * Запускается при устаревании кэша (со страницы аналитики). Тенант-контекст
 * восстанавливается из переданного tenantId; окно — пресет или произвольный
 * диапазон (period + from/to).
 */
final class RefreshLeadInsights implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public readonly string $tenantId,
        public readonly ?string $period,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
    ) {}

    public function handle(TenantInitializer $tenancy, LeadInsightsService $insights): void
    {
        $tenancy->run($this->tenantId, function () use ($insights): void {
            $insights->refresh(AnalyticsRange::resolve($this->period, $this->from, $this->to));
        });
    }
}
