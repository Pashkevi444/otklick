<?php

declare(strict_types=1);

namespace App\Modules\Knowledge;

use App\Modules\Knowledge\Contracts\KnowledgeApi;
use App\Modules\Knowledge\Models\KnowledgeEntry;
use App\Modules\Knowledge\Models\KnowledgeGap;
use App\Modules\Knowledge\Repositories\Contracts\CrmKnowledgeRepositoryInterface;
use App\Modules\Knowledge\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Modules\Knowledge\Repositories\Contracts\KnowledgeGapRepositoryInterface;
use App\Modules\Knowledge\Services\KnowledgeRetriever;
use Illuminate\Support\Collection;

/**
 * Фасад модуля «База знаний»: реализует {@see KnowledgeApi}, делегируя внутренним
 * репозиториям и ретриверу. Имена методов совпадают с внутренними — потребители
 * меняют только тип в конструкторе. Исключение: crmForCurrentTenant — так разведена
 * коллизия forCurrentTenant() между записями БЗ и CRM-записями.
 */
final class KnowledgeApiService implements KnowledgeApi
{
    public function __construct(
        private readonly KnowledgeEntryRepositoryInterface $entries,
        private readonly CrmKnowledgeRepositoryInterface $crmKnowledge,
        private readonly KnowledgeRetriever $retriever,
        private readonly KnowledgeGapRepositoryInterface $gaps,
    ) {}

    public function forCurrentTenant(): Collection
    {
        return $this->entries->forCurrentTenant();
    }

    public function publishedForCurrentTenant(): Collection
    {
        return $this->entries->publishedForCurrentTenant();
    }

    public function find(string $id): ?KnowledgeEntry
    {
        return $this->entries->find($id);
    }

    public function crmForCurrentTenant(): Collection
    {
        return $this->crmKnowledge->forCurrentTenant();
    }

    public function retrieve(string $question, int $k): ?array
    {
        return $this->retriever->retrieve($question, $k);
    }

    public function record(string $question, ?string $conversationId, ?string $channelType): KnowledgeGap
    {
        return $this->gaps->record($question, $conversationId, $channelType);
    }
}
