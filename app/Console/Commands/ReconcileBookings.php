<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\CrmConnectionRepositoryInterface;
use App\Tenancy\TenantInitializer;
use Illuminate\Console\Command;

/**
 * Раз в час сверяет записи с CRM: закрывает заказы, время визита которых уже
 * прошло (лид становится «Успешным» — см. Conversation::outcome). Работает ТОЛЬКО
 * у тенантов с активной CRM-интеграцией; для бизнеса без CRM ничего не делает
 * (никаких обменов/закрытий — лишний код не крутится). Запускается планировщиком
 * (см. routes/console.php).
 */
final class ReconcileBookings extends Command
{
    protected $signature = 'bookings:reconcile';

    protected $description = 'Закрывает завершённые записи (время визита прошло) у тенантов с CRM.';

    public function handle(
        TenantInitializer $tenancy,
        ConversationRepositoryInterface $conversations,
        CrmConnectionRepositoryInterface $connections,
    ): int {
        $closed = 0;

        Tenant::query()->pluck('id')->each(function (string $tenantId) use ($tenancy, $conversations, $connections, &$closed): void {
            $closed += $tenancy->run($tenantId, function () use ($conversations, $connections): int {
                // Нет CRM — запись всегда уходит на человека, обменов/закрытий нет.
                if ($connections->activeForCurrentTenant() === null) {
                    return 0;
                }

                return $conversations->closeCompletedBookingsForCurrentTenant(now());
            });
        });

        $this->info("Закрыто завершённых записей: {$closed}.");

        return self::SUCCESS;
    }
}
