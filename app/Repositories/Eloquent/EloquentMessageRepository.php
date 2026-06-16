<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTO\IncomingMessage;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentMessageRepository implements MessageRepositoryInterface
{
    public function recentForConversation(Conversation $conversation, int $limit): Collection
    {
        return Message::query()
            ->where('conversation_id', $conversation->id)
            ->latest()
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function allForConversation(Conversation $conversation): Collection
    {
        return Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function recordInbound(Conversation $conversation, IncomingMessage $incoming): ?Message
    {
        $alreadyRecorded = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', MessageDirection::Inbound)
            ->where('external_message_id', $incoming->externalMessageId)
            ->exists();

        if ($alreadyRecorded) {
            return null;
        }

        return Message::create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Inbound,
            'external_message_id' => $incoming->externalMessageId,
            'text' => $incoming->text,
            'payload' => $incoming->raw,
            'status' => MessageStatus::Received,
        ]);
    }

    public function recordOutbound(Conversation $conversation, string $text, MessageStatus $status): Message
    {
        return Message::create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound,
            'external_message_id' => null,
            'text' => $text,
            'status' => $status,
        ]);
    }
}
