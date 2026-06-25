<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Services;

use App\Modules\Knowledge\Models\CrmKnowledgeEntry;
use App\Modules\Knowledge\Models\KnowledgeEntry;
use App\Modules\Knowledge\Repositories\Contracts\CrmKnowledgeRepositoryInterface;
use App\Modules\Knowledge\Repositories\Contracts\KnowledgeChunkRepositoryInterface;
use App\Modules\Knowledge\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Shared\Llm\Contracts\Embedder;
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
