<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Repositories\Eloquent;

use App\Modules\Knowledge\DTO\KnowledgeEntryData;
use App\Modules\Knowledge\Models\KnowledgeEntry;
use App\Modules\Knowledge\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Shared\Repositories\EloquentRepository;
use Illuminate\Support\Collection;

/**
 * @extends EloquentRepository<KnowledgeEntry>
 */
final class EloquentKnowledgeEntryRepository extends EloquentRepository implements KnowledgeEntryRepositoryInterface
{
    protected function model(): string
    {
        return KnowledgeEntry::class;
    }

    public function forCurrentTenant(): Collection
    {
        return KnowledgeEntry::query()->latest()->get();
    }

    public function publishedForCurrentTenant(): Collection
    {
        return KnowledgeEntry::query()->where('is_published', true)->latest()->get();
    }

    public function find(string $id): ?KnowledgeEntry
    {
        return $this->findById($id);
    }

    public function create(KnowledgeEntryData $data): KnowledgeEntry
    {
        return KnowledgeEntry::create([
            'title' => $data->title,
            'content' => $data->content,
            'is_published' => $data->isPublished,
            'links' => $data->links,
            'images' => $data->images,
        ]);
    }

    public function update(KnowledgeEntry $entry, KnowledgeEntryData $data): KnowledgeEntry
    {
        $entry->update([
            'title' => $data->title,
            'content' => $data->content,
            'is_published' => $data->isPublished,
            'links' => $data->links,
            'images' => $data->images,
        ]);

        return $entry->refresh();
    }

    public function delete(KnowledgeEntry $entry): void
    {
        $this->remove($entry);
    }
}
