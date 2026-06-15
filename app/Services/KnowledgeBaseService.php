<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\KnowledgeEntryData;
use App\Models\KnowledgeEntry;
use App\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * База знаний тенанта. Скоупится текущим тенант-контекстом (его ставит
 * BindTenantToRequest). Здесь же в Фазе 3 появится генерация эмбеддингов.
 */
final readonly class KnowledgeBaseService
{
    public function __construct(
        private KnowledgeEntryRepositoryInterface $entries,
    ) {}

    /**
     * @return Collection<int, KnowledgeEntry>
     */
    public function list(): Collection
    {
        return $this->entries->forCurrentTenant();
    }

    public function create(KnowledgeEntryData $data): KnowledgeEntry
    {
        return $this->entries->create($data);
    }

    public function update(KnowledgeEntry $entry, KnowledgeEntryData $data): KnowledgeEntry
    {
        return $this->entries->update($entry, $data);
    }

    public function delete(KnowledgeEntry $entry): void
    {
        $this->entries->delete($entry);
    }
}
