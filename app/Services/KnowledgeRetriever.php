<?php

declare(strict_types=1);

namespace App\Services;

use App\Llm\Contracts\Embedder;
use App\Repositories\Contracts\KnowledgeChunkRepositoryInterface;
use Throwable;

/**
 * Семантический поиск релевантных знаний под вопрос клиента (RAG-ретривер):
 * эмбеддит запрос и возвращает id записей-источников (клиентских и из CRM),
 * чтобы в промпт попали только релевантные, а не вся база.
 *
 * Возвращает null при пустом индексе или сбое эмбеддера — вызывающий слой
 * мягко деградирует к «вся база в промпт» (бот не ломается).
 *
 * Не final/readonly намеренно — мокается в юнит-тестах ReplyComposer.
 */
class KnowledgeRetriever
{
    public function __construct(
        private readonly Embedder $embedder,
        private readonly KnowledgeChunkRepositoryInterface $chunks,
    ) {}

    /**
     * @return array{manual: list<string>, crm: list<string>}|null
     */
    public function retrieve(string $question, int $k): ?array
    {
        if (trim($question) === '') {
            return null;
        }

        try {
            $hits = $this->chunks->searchForCurrentTenant($this->embedder->embed($question), $k);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if ($hits->isEmpty()) {
            return null;
        }

        $manual = [];
        $crm = [];

        foreach ($hits as $hit) {
            if ($hit['entry_id'] === null) {
                continue;
            }
            if ($hit['source'] === 'crm') {
                $crm[] = $hit['entry_id'];
            } else {
                $manual[] = $hit['entry_id'];
            }
        }

        return ['manual' => $manual, 'crm' => $crm];
    }
}
