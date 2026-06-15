<?php

declare(strict_types=1);

namespace App\Services;

use App\Channels\Contracts\MessengerGateway;
use App\DTO\IncomingMessage;
use App\Enums\MessageStatus;
use App\Models\Channel;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Throwable;

/**
 * Обработка входящего сообщения: фиксация диалога и сообщения, формирование
 * ответа и отправка обратно в канал.
 *
 * Фаза 1 — эхо-шаблон. Дальше здесь подключатся RAG и LLM (Фазы 2–3).
 */
final readonly class IncomingMessageService
{
    public function __construct(
        private ConversationRepositoryInterface $conversations,
        private MessageRepositoryInterface $messages,
        private MessengerGateway $gateway,
    ) {}

    public function handle(Channel $channel, IncomingMessage $incoming): void
    {
        $conversation = $this->conversations->firstOrCreateForChat(
            $channel->id,
            $incoming->externalChatId,
            $incoming->contactName,
        );

        $inbound = $this->messages->recordInbound($conversation, $incoming);

        // Дубликат вебхука: сообщение уже обработано — выходим (идемпотентность).
        if ($inbound === null) {
            return;
        }

        $reply = $this->reply($incoming);

        $status = MessageStatus::Sent;

        try {
            $this->gateway->send($channel, $incoming->externalChatId, $reply);
        } catch (Throwable $e) {
            $status = MessageStatus::Failed;
            report($e);
        }

        $this->messages->recordOutbound($conversation, $reply, $status);
        $this->conversations->touchLastMessage($conversation);
    }

    /**
     * Шаблон ответа Фазы 1: эхо. Заменится конвейером RAG + LLM.
     */
    private function reply(IncomingMessage $incoming): string
    {
        return "Вы написали: {$incoming->text}";
    }
}
