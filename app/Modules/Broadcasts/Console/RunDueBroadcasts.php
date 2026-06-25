<?php

declare(strict_types=1);

namespace App\Modules\Broadcasts\Console;

use App\Modules\Broadcasts\Repositories\Contracts\BroadcastRepositoryInterface;
use App\Modules\Broadcasts\Services\BroadcastService;
use App\Shared\Models\Tenant;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Console\Command;

/**
 * Запускает «созревшие» запланированные рассылки (next_run_at ≤ сейчас) у
 * тенантов с правом на рассылки. Реальная доставка — в очереди (Horizon).
 * Периодичные рассылки сами переносят next_run_at после доставки. У тенантов без
 * права на рассылки ничего не крутится. Запускается планировщиком.
 */
final class RunDueBroadcasts extends Command
{
    protected $signature = 'broadcasts:run-due';

    protected $description = 'Запускает запланированные рассылки, у которых наступило время.';

    public function handle(
        TenantInitializer $tenancy,
        BroadcastRepositoryInterface $broadcasts,
        BroadcastService $service,
    ): int {
        $launched = 0;

        Tenant::query()->pluck('id')->each(function (string $tenantId) use ($tenancy, $broadcasts, $service, &$launched): void {
            $launched += $tenancy->run($tenantId, function () use ($tenantId, $broadcasts, $service): int {
                $tenant = Tenant::query()->find($tenantId);
                if ($tenant === null || ! $tenant->features()->broadcasts) {
                    return 0;
                }

                $count = 0;
                foreach ($broadcasts->dueForCurrentTenant(now()) as $broadcast) {
                    $service->launchNow($broadcast);
                    $count++;
                }

                return $count;
            });
        });

        $this->info("Запущено рассылок: {$launched}.");

        return self::SUCCESS;
    }
}
