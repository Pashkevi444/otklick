<?php

declare(strict_types=1);

namespace App\Modules\Knowledge;

use App\Modules\Knowledge\Contracts\KnowledgeApi;
use App\Modules\Knowledge\Repositories\Contracts\CrmKnowledgeRepositoryInterface;
use App\Modules\Knowledge\Repositories\Contracts\KnowledgeChunkRepositoryInterface;
use App\Modules\Knowledge\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Modules\Knowledge\Repositories\Contracts\KnowledgeGapRepositoryInterface;
use App\Modules\Knowledge\Repositories\Eloquent\EloquentCrmKnowledgeRepository;
use App\Modules\Knowledge\Repositories\Eloquent\EloquentKnowledgeChunkRepository;
use App\Modules\Knowledge\Repositories\Eloquent\EloquentKnowledgeEntryRepository;
use App\Modules\Knowledge\Repositories\Eloquent\EloquentKnowledgeGapRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Модуль «База знаний» (RAG): элементы БЗ + векторные чанки (pgvector), пробелы
 * знаний и их AI-черновики, импорт с сайта, синк знаний из CRM. Эмбеддер (App\Llm)
 * и порт CRM (App\Crm) — общие зависимости, импортируются явно. Промпт бота
 * (PromptTemplate) живёт в модуле Bot — БЗ обращается к нему через его репозиторий.
 */
final class KnowledgeServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        KnowledgeEntryRepositoryInterface::class => EloquentKnowledgeEntryRepository::class,
        KnowledgeChunkRepositoryInterface::class => EloquentKnowledgeChunkRepository::class,
        KnowledgeGapRepositoryInterface::class => EloquentKnowledgeGapRepository::class,
        CrmKnowledgeRepositoryInterface::class => EloquentCrmKnowledgeRepository::class,
        KnowledgeApi::class => KnowledgeApiService::class,
    ];
}
