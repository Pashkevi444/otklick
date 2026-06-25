<?php

declare(strict_types=1);

namespace App\Modules\Bot;

use App\Modules\Bot\Contracts\BotApi;
use App\Modules\Bot\Repositories\Contracts\PromptTemplateRepositoryInterface;
use App\Modules\Bot\Repositories\Eloquent\EloquentPromptTemplateRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Модуль «Бот» (мозг ответа): BotResponder выбирает, кто отвечает (база знаний
 * через ReplyComposer/PromptBuilder, мастер записи или сценарий), промпт бота по
 * нише (PromptTemplate). Зависит от Knowledge/Flows/Booking/Conversations — это
 * межмодульные вызовы публичных сервисов (будущие сетевые границы). «Голову»
 * промпта правит СУ в модуле Platform через репозиторий этого модуля.
 */
final class BotServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        PromptTemplateRepositoryInterface::class => EloquentPromptTemplateRepository::class,
        BotApi::class => BotApiService::class,
    ];
}
