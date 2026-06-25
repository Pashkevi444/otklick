<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Jobs;

use App\Modules\Knowledge\Services\KnowledgeIndexer;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Фоновая пересборка векторного индекса знаний тенанта (RAG). Ставится в очередь
 * при изменении базы знаний и после выгрузки данных из CRM.
 */
final class IndexKnowledge implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public readonly string $tenantId) {}

    public function handle(TenantInitializer $tenancy, KnowledgeIndexer $indexer): void
    {
        $tenancy->run($this->tenantId, fn () => $indexer->reindex());
    }
}
