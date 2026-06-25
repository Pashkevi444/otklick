<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Jobs;

use App\Modules\Knowledge\Services\CrmKnowledgeSyncService;
use App\Modules\Knowledge\Services\CrmSyncStatus;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Фоновая выгрузка справочника CRM (услуги/мастера/филиал) в базу знаний CRM.
 * По кнопке в кабинете задача вешается на очередь и аккуратно тянет данные в
 * фоне, не трогая клиентскую базу знаний.
 */
final class SyncCrmKnowledge implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public readonly string $tenantId) {}

    public function handle(TenantInitializer $tenancy, CrmKnowledgeSyncService $sync, CrmSyncStatus $status): void
    {
        $status->begin($this->tenantId);

        try {
            $tenancy->run(
                $this->tenantId,
                fn () => $sync->sync(fn (int $percent) => $status->report($this->tenantId, $percent)),
            );
            $status->succeed($this->tenantId);

            // Свежие данные из CRM сразу попадают в векторный индекс (RAG).
            IndexKnowledge::dispatch($this->tenantId);
        } catch (Throwable $e) {
            $status->fail($this->tenantId);

            throw $e;
        }
    }
}
