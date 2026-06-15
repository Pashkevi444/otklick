<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\ConversationStatus;
use App\Models\Conversation;

/**
 * Контракт доступа к данным диалогов.
 */
interface ConversationRepositoryInterface
{
    public function updateStatus(Conversation $conversation, ConversationStatus $status): void;

    /**
     * Находит диалог по чату канала или создаёт новый. tenant_id проставляется
     * автоматически из текущего тенант-контекста.
     */
    public function firstOrCreateForChat(string $channelId, string $externalChatId, ?string $contactName): Conversation;

    public function touchLastMessage(Conversation $conversation): void;
}
