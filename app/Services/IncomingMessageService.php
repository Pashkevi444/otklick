<?php

declare(strict_types=1);

namespace App\Services;

use App\Channels\Contracts\MessengerGateway;
use App\DTO\IncomingMessage;
use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use App\Models\Channel;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Support\PhoneExtractor;
use Throwable;

/**
 * Обработка входящего сообщения: фиксация диалога и сообщения, генерация ответа
 * по базе знаний (ReplyComposer) и отправка обратно в канал.
 *
 * Если бот не знает ответа — отправляется вежливый фолбек, а диалог помечается
 * статусом «нужен человек».
 */
final readonly class IncomingMessageService
{
    public function __construct(
        private ConversationRepositoryInterface $conversations,
        private MessageRepositoryInterface $messages,
        private MessengerGateway $gateway,
        private ReplyComposer $composer,
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

        // Телефон для обратной связи — сохраняем по клиенту, если ещё не задан.
        if ($conversation->contact_phone === null) {
            $phone = PhoneExtractor::fromText($incoming->text);
            if ($phone !== null) {
                $this->conversations->setContactPhone($conversation, $phone);
            }
        }

        $reply = $this->composer->compose($channel->tenant, $conversation);

        $status = MessageStatus::Sent;

        try {
            $this->gateway->send($channel, $incoming->externalChatId, $reply->text);
        } catch (Throwable $e) {
            $status = MessageStatus::Failed;
            report($e);
        }

        $this->messages->recordOutbound($conversation, $reply->text, $status);
        $this->conversations->touchLastMessage($conversation);

        if ($reply->escalate) {
            $this->conversations->updateStatus($conversation, ConversationStatus::NeedsHuman);
        }
    }
}
