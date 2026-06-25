<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Services;

use App\Modules\Channels\Contracts\ChannelsApi;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Conversations\Repositories\Contracts\ConversationRepositoryInterface;
use App\Modules\Conversations\Repositories\Contracts\MessageRepositoryInterface;
use App\Shared\Enums\MessageStatus;
use Throwable;

/**
 * Перехват диалога оператором (живой чат). Пока диалог перехвачен —
 * {@see Conversation::isOperatorHandling()} — бот молчит (подавление в
 * {@see WebWidgetService}/{@see IncomingMessageService}), а оператор пишет клиенту
 * из кабинета. В мессенджеры уходит короткая строка «оператор на связи»; в веб-
 * виджете оператора обозначает баннер (состояние из поллинга, без отдельной реплики).
 *
 * Вызывается в тенант-контексте (его ставит контроллер).
 */
final readonly class ConversationHandoffService
{
    public function __construct(
        private ConversationRepositoryInterface $conversations,
        private MessageRepositoryInterface $messages,
        private ChannelsApi $gateways,
    ) {}

    /** Оператор перехватывает диалог у бота. */
    public function takeOver(Conversation $conversation, ?int $operatorUserId): void
    {
        $this->conversations->setOperator($conversation, $operatorUserId);
        $this->announce($conversation, '👤 На связи оператор — отвечу вам лично.');
    }

    /** Оператор возвращает диалог боту. */
    public function release(Conversation $conversation): void
    {
        $this->conversations->clearOperator($conversation);
        $this->announce($conversation, '🤖 Снова на связи бот-администратор. Спрашивайте!');
    }

    /**
     * Ответ оператора клиенту (и продление перехвата — сдвиг авто-возврата).
     * $images — список URL картинок (оператор может приложить фото к ответу).
     *
     * @param  list<string>  $images
     */
    public function reply(Conversation $conversation, string $text, array $images = []): Message
    {
        $this->conversations->touchOperator($conversation);

        return $this->send($conversation, $text, $images);
    }

    /**
     * Системная пометка «оператор на связи / снова бот» — только в push-каналы
     * (мессенджеры) отдельной строкой. В веб-виджете её роль играет баннер из
     * состояния поллинга, поэтому туда строку не шлём.
     */
    private function announce(Conversation $conversation, string $text): void
    {
        $channel = $conversation->channel;

        if ($channel !== null && $this->gateways->has($channel->type)) {
            $this->send($conversation, $text);
        }
    }

    /**
     * Записывает исходящее и доставляет его в канал: в мессенджеры — пушем через
     * шлюз, в веб-виджет — только запись (виджет заберёт поллингом). Сбой пуша не
     * роняет действие оператора.
     */
    /**
     * @param  list<string>  $images
     */
    private function send(Conversation $conversation, string $text, array $images = []): Message
    {
        $message = $this->messages->recordOutbound($conversation, $text, MessageStatus::Sent, $images);
        $this->conversations->touchLastMessage($conversation);

        $channel = $conversation->channel;
        if ($channel !== null && $this->gateways->has($channel->type)) {
            try {
                $this->gateways->for($channel->type)->send($channel, (string) $conversation->external_chat_id, $text, null, $images);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $message;
    }
}
