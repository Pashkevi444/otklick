<?php

declare(strict_types=1);

namespace App\Modules\Flows;

use App\Modules\Flows\Contracts\FlowsApi;
use App\Modules\Flows\Repositories\Contracts\FlowAbRepositoryInterface;
use App\Modules\Flows\Repositories\Contracts\FlowRepositoryInterface;
use App\Modules\Flows\Repositories\Eloquent\EloquentFlowAbRepository;
use App\Modules\Flows\Repositories\Eloquent\EloquentFlowRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Модуль «Сценарии» (no-code конструктор воронок): FlowEngine встроен в ответ бота
 * между автоматом записи и LLM; A/B-назначения, готовые шаблоны сценариев. Запись
 * (BookingFlow) и база знаний (Knowledge) — межмодульные зависимости (явные импорты).
 */
final class FlowsServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        FlowRepositoryInterface::class => EloquentFlowRepository::class,
        FlowAbRepositoryInterface::class => EloquentFlowAbRepository::class,
        FlowsApi::class => FlowsApiService::class,
    ];
}
