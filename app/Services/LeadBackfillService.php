<?php

declare(strict_types=1);

namespace App\Services;

use App\Tenancy\TenantInitializer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Бэкфилл лидов из диалогов: у каждого тенанта диалоги с привязанным клиентом
 * (`conversations.client_id`) превращаются в лид (входящее, source=bot,
 * status=new). Идемпотентно (по `conversation_id`). Контекст тенанта ставится
 * через {@see TenantInitializer::run} — RLS соблюдён. Вызывается из миграции
 * (и пригоден к повторному запуску вручную через tinker).
 */
final class LeadBackfillService
{
    public function __construct(private readonly TenantInitializer $tenants) {}

    /** @return int сколько лидов создано */
    public function run(): int
    {
        $now = now();
        $created = 0;

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $created += $this->tenants->run((string) $tenantId, function () use ($tenantId, $now): int {
                $conversations = DB::table('conversations')
                    ->where('tenant_id', $tenantId)
                    ->whereNotNull('client_id')
                    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                        ->from('leads')
                        ->whereColumn('leads.conversation_id', 'conversations.id'))
                    ->get(['id', 'tenant_id', 'client_id']);

                $rows = $conversations->map(fn ($c): array => [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $c->tenant_id,
                    'client_id' => $c->client_id,
                    'conversation_id' => $c->id,
                    'status' => 'new',
                    'source' => 'bot',
                    'title' => null,
                    'notes' => null,
                    'custom' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('leads')->insert($chunk);
                }

                return count($rows);
            });
        }

        return $created;
    }
}
