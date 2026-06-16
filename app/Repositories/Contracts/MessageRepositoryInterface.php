<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTO\IncomingMessage;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Collection;

/**
 * Контракт доступа к данным сообщений.
 */
interface MessageRepositoryInterface
{
    /**
     * Последние сообщения диалога в хронологическом порядке (для истории в LLM).
     *
     * @return Collection<int, Message>
     */
    public function recentForConversation(Conversation $conversation, int $limit): Collection;

    /**
     * Все сообщения диалога в хронологическом порядке (для журнала переписок).
     *
     * @return Collection<int, Message>
     */
    public function allForConversation(Conversation $conversation): Collection;

    /**
     * Сохраняет входящее сообщение. Возвращает null, если сообщение с таким
     * external_message_id уже записано в этом диалоге (идемпотентность ретраев).
     */
    public function recordInbound(Conversation $conversation, IncomingMessage $incoming): ?Message;

    public function recordOutbound(Conversation $conversation, string $text, MessageStatus $status): Message;

    /**
     * Текст последней реплики бота в диалоге (для контекста — например, чтобы
     * понять, спрашивал ли бот имя). null, если бот ещё ничего не отправлял.
     */
    public function latestOutboundText(Conversation $conversation): ?string;
}
