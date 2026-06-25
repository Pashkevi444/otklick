<?php

declare(strict_types=1);

namespace App\Modules\Clients\Jobs;

use App\Modules\Clients\Repositories\Contracts\ClientRepositoryInterface;
use App\Modules\Clients\Services\ClientSummaryService;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Фоновая пересборка краткого резюме клиента (LLM) — по записи клиента или вручную.
 * Исполняется в тенант-контексте; при сбое LLM прежнее резюме не затирается.
 */
final class RefreshClientSummary implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $clientId,
    ) {}

    public function handle(
        TenantInitializer $tenancy,
        ClientRepositoryInterface $clients,
        ClientSummaryService $summaries,
    ): void {
        $tenancy->run($this->tenantId, function () use ($clients, $summaries): void {
            $client = $clients->find($this->clientId);

            if ($client === null) {
                return;
            }

            $summary = $summaries->summarize($client);

            if ($summary !== null) {
                $clients->update($client, ['summary' => $summary, 'summary_generated_at' => now()]);
            }
        });
    }
}
