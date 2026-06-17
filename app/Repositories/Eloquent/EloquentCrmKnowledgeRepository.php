<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\CrmKnowledgeEntry;
use App\Repositories\Contracts\CrmKnowledgeRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentCrmKnowledgeRepository implements CrmKnowledgeRepositoryInterface
{
    public function forCurrentTenant(): Collection
    {
        return CrmKnowledgeEntry::query()
            ->orderBy('category')
            ->orderBy('title')
            ->get();
    }

    public function replaceForCurrentTenant(array $rows): void
    {
        // Атомарно: старая выгрузка удаляется и кладётся свежая — частичного
        // состояния не остаётся (ACID). tenant_id проставит BelongsToTenant.
        DB::transaction(function () use ($rows): void {
            CrmKnowledgeEntry::query()->delete();

            foreach ($rows as $row) {
                CrmKnowledgeEntry::create([
                    'category' => $row['category'],
                    'external_id' => $row['external_id'],
                    'title' => $row['title'],
                    'content' => $row['content'],
                    'meta' => $row['meta'],
                ]);
            }
        });
    }
}
