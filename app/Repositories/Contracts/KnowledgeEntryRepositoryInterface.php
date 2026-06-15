<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTO\KnowledgeEntryData;
use App\Models\KnowledgeEntry;
use Illuminate\Support\Collection;

/**
 * Контракт доступа к данным базы знаний. Скоупится текущим тенантом (scoped/RLS).
 */
interface KnowledgeEntryRepositoryInterface
{
    /**
     * @return Collection<int, KnowledgeEntry>
     */
    public function forCurrentTenant(): Collection;

    /**
     * Только опубликованные записи текущего тенанта (для ответов бота).
     *
     * @return Collection<int, KnowledgeEntry>
     */
    public function publishedForCurrentTenant(): Collection;

    public function find(string $id): ?KnowledgeEntry;

    public function create(KnowledgeEntryData $data): KnowledgeEntry;

    public function update(KnowledgeEntry $entry, KnowledgeEntryData $data): KnowledgeEntry;

    public function delete(KnowledgeEntry $entry): void;
}
