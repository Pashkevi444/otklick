<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\LeadAnalyticsPeriod;
use App\Services\LeadInsightsService;
use App\Tenancy\TenantInitializer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Пересчёт ИИ-разбора лидов в фоне (Horizon), чтобы не звать LLM в HTTP-запросе.
 * Запускается при устаревании кэша (с дашборда аналитики). Тенант-контекст
 * восстанавливается из переданного tenantId.
 */
final class RefreshLeadInsights implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $period,
    ) {}

    public function handle(TenantInitializer $tenancy, LeadInsightsService $insights): void
    {
        $tenancy->run($this->tenantId, function () use ($insights): void {
            $insights->refresh(LeadAnalyticsPeriod::fromValue($this->period));
        });
    }
}
