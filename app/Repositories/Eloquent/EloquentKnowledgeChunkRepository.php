<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\KnowledgeChunkRepositoryInterface;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class EloquentKnowledgeChunkRepository implements KnowledgeChunkRepositoryInterface
{
    public function __construct(private TenantContext $tenant) {}

    public function replaceForCurrentTenant(array $rows): void
    {
        $tenantId = (string) $this->tenant->id();
        $pgsql = $this->isPgsql();

        DB::transaction(function () use ($rows, $tenantId, $pgsql): void {
            DB::table('knowledge_chunks')->where('tenant_id', $tenantId)->delete();

            foreach ($rows as $row) {
                $base = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'source' => $row['source'],
                    'entry_id' => $row['entry_id'],
                    'content' => $row['content'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($pgsql) {
                    $base['embedding'] = DB::raw("'".$this->literal($row['embedding'])."'::vector");
                } else {
                    $base['embedding'] = json_encode($row['embedding']);
                }

                DB::table('knowledge_chunks')->insert($base);
            }
        });
    }

    public function searchForCurrentTenant(array $queryEmbedding, int $k): array
    {
        $tenantId = (string) $this->tenant->id();

        if ($this->isPgsql()) {
            $rows = DB::select(
                'SELECT source, entry_id FROM knowledge_chunks WHERE tenant_id = ? ORDER BY embedding <=> ?::vector LIMIT ?',
                [$tenantId, $this->literal($queryEmbedding), $k],
            );

            return array_map(fn (object $r): array => [
                'source' => (string) $r->source,
                'entry_id' => $r->entry_id !== null ? (string) $r->entry_id : null,
            ], $rows);
        }

        // sqlite (тесты): косинус в PHP по чанкам тенанта.
        return DB::table('knowledge_chunks')
            ->where('tenant_id', $tenantId)
            ->get(['source', 'entry_id', 'embedding'])
            ->map(fn (object $r): array => [
                'source' => (string) $r->source,
                'entry_id' => $r->entry_id !== null ? (string) $r->entry_id : null,
                'score' => $this->cosine($queryEmbedding, (array) json_decode((string) $r->embedding, true)),
            ])
            ->sortByDesc('score')
            ->take($k)
            ->map(fn (array $r): array => ['source' => $r['source'], 'entry_id' => $r['entry_id']])
            ->values()
            ->all();
    }

    private function isPgsql(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    /**
     * @param  list<float>  $vector
     */
    private function literal(array $vector): string
    {
        return '['.implode(',', array_map(static function (float $v): string {
            $s = rtrim(rtrim(sprintf('%.8f', $v), '0'), '.');

            return $s === '' || $s === '-' ? '0' : $s;
        }, $vector)).']';
    }

    /**
     * @param  list<float>  $a
     * @param  array<int, float>  $b
     */
    private function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;

        foreach ($a as $i => $va) {
            $vb = (float) ($b[$i] ?? 0.0);
            $dot += $va * $vb;
            $na += $va * $va;
            $nb += $vb * $vb;
        }

        $denom = sqrt($na) * sqrt($nb);

        return $denom > 0.0 ? $dot / $denom : 0.0;
    }
}
