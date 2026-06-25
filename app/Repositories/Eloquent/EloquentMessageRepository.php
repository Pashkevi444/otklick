<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTO\IncomingMessage;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
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

    public function recentForChat(string $channelId, string $externalChatId, int $limit): Collection
    {
        return Message::query()
            ->whereHas('conversation', fn (Builder $q): Builder => $q
                ->where('channel_id', $channelId)
                ->where('external_chat_id', $externalChatId))
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

    public function sinceForConversation(Conversation $conversation, ?string $afterId): Collection
    {
        return Message::query()
            ->where('conversation_id', $conversation->id)
            ->when($afterId !== null && $afterId !== '', fn ($q) => $q->where('id', '>', $afterId))
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

    public function recordOutbound(Conversation $conversation, string $text, MessageStatus $status, array $images = []): Message
    {
        return Message::create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound,
            'external_message_id' => null,
            'text' => $text,
            // URL картинок — в payload.images (как у входящих фото клиента), чтобы
            // кабинет и веб-виджет их отрисовали.
            'payload' => $images !== [] ? ['images' => $images] : null,
            'status' => $status,
        ]);
    }

    public function markStatusById(string $messageId, MessageStatus $status): void
    {
        // Скоупится текущим тенантом (RLS + глобальный scope) — id чужого
        // тенанта просто не найдётся.
        Message::query()->whereKey($messageId)->update(['status' => $status]);
    }

    public function latestOutboundText(Conversation $conversation): ?string
    {
        $text = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', MessageDirection::Outbound)
            ->latest('created_at')
            ->latest('id')
            ->value('text');

        return $text !== null ? (string) $text : null;
    }
}
