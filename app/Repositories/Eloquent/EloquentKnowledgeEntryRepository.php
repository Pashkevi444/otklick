<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTO\KnowledgeEntryData;
use App\Models\KnowledgeEntry;
use App\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentKnowledgeEntryRepository implements KnowledgeEntryRepositoryInterface
{
    public function forCurrentTenant(): Collection
    {
        return KnowledgeEntry::query()->latest()->get();
    }

    public function find(string $id): ?KnowledgeEntry
    {
        return KnowledgeEntry::find($id);
    }

    public function create(KnowledgeEntryData $data): KnowledgeEntry
    {
        return KnowledgeEntry::create([
            'title' => $data->title,
            'content' => $data->content,
            'is_published' => $data->isPublished,
        ]);
    }

    public function update(KnowledgeEntry $entry, KnowledgeEntryData $data): KnowledgeEntry
    {
        $entry->update([
            'title' => $data->title,
            'content' => $data->content,
            'is_published' => $data->isPublished,
        ]);

        return $entry->refresh();
    }

    public function delete(KnowledgeEntry $entry): void
    {
        $entry->delete();
    }
}
