<?php

declare(strict_types=1);

namespace App\Modules\Broadcasts\Jobs;

use App\Modules\Broadcasts\Repositories\Contracts\BroadcastRepositoryInterface;
use App\Modules\Broadcasts\Services\BroadcastService;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Доставка одной рассылки (через очередь Horizon). tries=1: рассылка идёт по всей
 * базе одним проходом, ретрай дублировал бы уже отправленные сообщения. Ошибки
 * отдельных получателей перехватываются внутри сервиса и не валят весь проход.
 */
final class SendBroadcast implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $broadcastId,
    ) {}

    public function handle(
        TenantInitializer $tenancy,
        BroadcastRepositoryInterface $broadcasts,
        BroadcastService $service,
    ): void {
        $tenancy->run($this->tenantId, function () use ($broadcasts, $service): void {
            $broadcast = $broadcasts->find($this->broadcastId);

            if ($broadcast === null) {
                return;
            }

            $service->deliver($broadcast);
        });
    }
}
