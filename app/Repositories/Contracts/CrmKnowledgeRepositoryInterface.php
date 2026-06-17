<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\CrmKnowledgeEntry;
use Illuminate\Support\Collection;

/**
 * Контракт доступа к базе знаний из CRM (нередактируемой). Скоупится текущим
 * тенантом (scoped/RLS).
 */
interface CrmKnowledgeRepositoryInterface
{
    /**
     * Все CRM-записи текущего тенанта (для вкладки и промпта бота).
     *
     * @return Collection<int, CrmKnowledgeEntry>
     */
    public function forCurrentTenant(): Collection;

    /**
     * Полностью заменяет CRM-базу знаний тенанта свежей выгрузкой (delete+insert
     * атомарно). Так данные из CRM всегда актуальны, а устаревшие удаляются.
     *
     * @param  list<array{category: string, external_id: string|null, title: string, content: string, meta: array<string, mixed>|null}>  $rows
     */
    public function replaceForCurrentTenant(array $rows): void;
}
