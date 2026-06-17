<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\CrmKnowledgeSyncService;
use App\Tenancy\TenantInitializer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Фоновая выгрузка справочника CRM (услуги/мастера/филиал) в базу знаний CRM.
 * По кнопке в кабинете задача вешается на очередь и аккуратно тянет данные в
 * фоне, не трогая клиентскую базу знаний.
 */
final class SyncCrmKnowledge implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public readonly string $tenantId) {}

    public function handle(TenantInitializer $tenancy, CrmKnowledgeSyncService $sync): void
    {
        $tenancy->run($this->tenantId, fn () => $sync->sync());
    }
}
