<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

/**
 * Векторный индекс знаний (RAG). Скоупится текущим тенантом (RLS на pgsql +
 * явный фильтр). Поиск: pgvector `<=>` на PostgreSQL, косинус в PHP на sqlite.
 */
interface KnowledgeChunkRepositoryInterface
{
    /**
     * Полностью заменяет индекс тенанта свежими чанками (delete+insert атомарно).
     *
     * @param  list<array{source: string, entry_id: string|null, content: string, embedding: list<float>}>  $rows
     */
    public function replaceForCurrentTenant(array $rows): void;

    /**
     * Топ-K наиболее близких к запросу чанков (по эмбеддингу).
     *
     * @param  list<float>  $queryEmbedding
     * @return list<array{source: string, entry_id: string|null}>
     */
    public function searchForCurrentTenant(array $queryEmbedding, int $k): array;
}
