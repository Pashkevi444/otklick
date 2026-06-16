<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentConversationRepository implements ConversationRepositoryInterface
{
    public function updateStatus(Conversation $conversation, ConversationStatus $status): void
    {
        $conversation->forceFill(['status' => $status])->save();
    }

    public function forCurrentTenant(): Collection
    {
        return Conversation::query()
            ->with(['channel', 'latestMessage'])
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function findForCurrentTenant(string $id): ?Conversation
    {
        return Conversation::query()->with('channel')->find($id);
    }

    public function firstOrCreateForChat(string $channelId, string $externalChatId, ?string $contactName): Conversation
    {
        return Conversation::firstOrCreate(
            [
                'channel_id' => $channelId,
                'external_chat_id' => $externalChatId,
            ],
            [
                'contact_name' => $contactName,
                'status' => ConversationStatus::Open,
            ],
        );
    }

    public function touchLastMessage(Conversation $conversation): void
    {
        $conversation->forceFill(['last_message_at' => now()])->save();
    }
}
