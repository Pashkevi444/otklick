<?php

declare(strict_types=1);

namespace App\Modules\Conversations;

use App\Modules\Conversations\Console\CloseStaleConversations;
use App\Modules\Conversations\Console\ReleaseIdleOperators;
use App\Modules\Conversations\Repositories\Contracts\ConversationRepositoryInterface;
use App\Modules\Conversations\Repositories\Contracts\MessageRepositoryInterface;
use App\Modules\Conversations\Repositories\Eloquent\EloquentConversationRepository;
use App\Modules\Conversations\Repositories\Eloquent\EloquentMessageRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Модуль «Диалоги» (инбокс): диалоги и сообщения, перехват оператором (живой чат),
 * веб-виджет, рубежи согласия/контакта/антиспама, приём входящего (IncomingMessageService),
 * realtime-события (печатает…/активность). «Лид» в кабинете = Conversation; ссылка на
 * клиента — межмодульный шов (модуль Clients). MessageObserver регистрируется атрибутом
 * #[ObservedBy] на модели Message. Ответ бота генерит модуль Bot (вызывается отсюда).
 */
final class ConversationsServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        ConversationRepositoryInterface::class => EloquentConversationRepository::class,
        MessageRepositoryInterface::class => EloquentMessageRepository::class,
    ];

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CloseStaleConversations::class, ReleaseIdleOperators::class]);
        }
    }
}
