<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Services\NotificationService;
use App\Shared\Enums\OwnerEvent;
use App\Shared\Models\Tenant;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Фоновая рассылка уведомления владельцу о событии (Horizon) — чтобы не держать
 * вебхук/HTTP-ответ. Восстанавливает тенант-контекст из tenantId.
 */
final class SendOwnerNotification implements ShouldQueue
{
    use Dispatchable, Queueable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $event,
        public readonly array $context = [],
    ) {}

    public function handle(TenantInitializer $tenancy, NotificationService $service): void
    {
        $tenancy->run($this->tenantId, function () use ($service): void {
            $tenant = Tenant::find($this->tenantId);

            if ($tenant === null) {
                return;
            }

            $service->send($tenant, OwnerEvent::from($this->event), $this->context);
        });
    }
}
