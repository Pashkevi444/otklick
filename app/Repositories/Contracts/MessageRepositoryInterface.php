<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTO\IncomingMessage;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\Message;

/**
 * Контракт доступа к данным сообщений.
 */
interface MessageRepositoryInterface
{
    /**
     * Сохраняет входящее сообщение. Возвращает null, если сообщение с таким
     * external_message_id уже записано в этом диалоге (идемпотентность ретраев).
     */
    public function recordInbound(Conversation $conversation, IncomingMessage $incoming): ?Message;

    public function recordOutbound(Conversation $conversation, string $text, MessageStatus $status): Message;
}
