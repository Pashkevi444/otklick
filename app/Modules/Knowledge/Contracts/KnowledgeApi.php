<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Contracts;

use App\Modules\Knowledge\KnowledgeApiService;
use App\Modules\Knowledge\Models\CrmKnowledgeEntry;
use App\Modules\Knowledge\Models\KnowledgeEntry;
use App\Modules\Knowledge\Models\KnowledgeGap;
use Illuminate\Support\Collection;

/**
 * Публичный контракт модуля «База знаний» — единственная дверь для других модулей.
 * Снаружи доступны только эти методы; KnowledgeRetriever, репозитории и джобы —
 * приватная кухня модуля. Реализация — {@see KnowledgeApiService}.
 */
interface KnowledgeApi
{
    /**
     * Все записи БЗ текущего тенанта (редактируемые — для конструктора воронок).
     *
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

    /**
     * Все CRM-записи БЗ текущего тенанта (нередактируемые — для промпта бота).
     *
     * @return Collection<int, CrmKnowledgeEntry>
     */
    public function crmForCurrentTenant(): Collection;

    /**
     * Семантический поиск (RAG): id релевантных записей-источников под вопрос
     * клиента. null — индекс пуст или эмбеддер недоступен (вызывающий слой мягко
     * деградирует к «вся база в промпт»).
     *
     * @return array{manual: list<string>, crm: list<string>}|null
     */
    public function retrieve(string $question, int $k): ?array;

    /**
     * Фиксирует «пробел бота» (вопрос без ответа): создаёт новый или наращивает
     * счётчик уже открытого по нормализованному тексту.
     */
    public function record(string $question, ?string $conversationId, ?string $channelType): KnowledgeGap;
}
