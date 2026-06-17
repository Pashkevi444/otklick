<?php

declare(strict_types=1);

namespace App\Services;

use App\Llm\Contracts\Embedder;
use App\Models\CrmKnowledgeEntry;
use App\Models\KnowledgeEntry;
use App\Repositories\Contracts\CrmKnowledgeRepositoryInterface;
use App\Repositories\Contracts\KnowledgeChunkRepositoryInterface;
use App\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use Illuminate\Support\Facades\Log;

/**
 * Строит векторный индекс знаний тенанта (RAG): берёт опубликованные записи
 * клиентской базы и записи из CRM, считает эмбеддинги и полностью пересобирает
 * индекс (`knowledge_chunks`). Запускается в тенант-контексте фоновой задачей.
 */
final readonly class KnowledgeIndexer
{
    public function __construct(
        private KnowledgeEntryRepositoryInterface $knowledge,
        private CrmKnowledgeRepositoryInterface $crmKnowledge,
        private Embedder $embedder,
        private KnowledgeChunkRepositoryInterface $chunks,
    ) {}

    public function reindex(): void
    {
        $rows = [];

        foreach ($this->knowledge->publishedForCurrentTenant() as $entry) {
            /** @var KnowledgeEntry $entry */
            $text = trim($entry->title."\n".$entry->content);
            $rows[] = [
                'source' => 'manual',
                'entry_id' => $entry->id,
                'content' => $text,
                'embedding' => $this->embedder->embed($text),
            ];
        }

        foreach ($this->crmKnowledge->forCurrentTenant() as $entry) {
            /** @var CrmKnowledgeEntry $entry */
            $text = trim($entry->title.': '.$entry->content);
            $rows[] = [
                'source' => 'crm',
                'entry_id' => $entry->id,
                'content' => $text,
                'embedding' => $this->embedder->embed($text),
            ];
        }

        $this->chunks->replaceForCurrentTenant($rows);

        Log::info('knowledge.reindex.done', ['chunks' => count($rows)]);
    }
}
