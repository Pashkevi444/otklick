<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentConversationRepository implements ConversationRepositoryInterface
{
    public function updateStatus(Conversation $conversation, ConversationStatus $status): void
    {
        $conversation->forceFill(['status' => $status])->save();
    }

    public function markBooked(Conversation $conversation): void
    {
        $conversation->forceFill([
            'status' => ConversationStatus::Closed,
            'booked_at' => now(),
        ])->save();
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

    public function paginateForCurrentTenant(
        ?string $search,
        ?ConversationStatus $status,
        string $sort,
        string $direction,
        int $perPage,
    ): LengthAwarePaginator {
        $query = Conversation::query()
            ->with(['channel', 'latestMessage'])
            ->withCount('messages');

        if ($search !== null && $search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $w) use ($needle): void {
                $w->whereRaw('lower(contact_name) like ?', [$needle])
                    ->orWhereRaw('lower(contact_phone) like ?', [$needle])
                    ->orWhereRaw('lower(external_chat_id) like ?', [$needle])
                    ->orWhereHas('messages', fn (Builder $m) => $m->whereRaw('lower(text) like ?', [$needle]));
            });
        }

        if ($status instanceof ConversationStatus) {
            $query->where('status', $status);
        }

        $column = match ($sort) {
            'contact' => 'contact_name',
            'messages' => 'messages_count',
            default => 'last_message_at',
        };
        $dir = $direction === 'asc' ? 'asc' : 'desc';

        if ($column === 'messages_count') {
            $query->orderBy('messages_count', $dir);
        } else {
            $query->orderBy($column, $dir);
        }

        return $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();
    }

    public function firstOrCreateForChat(string $channelId, string $externalChatId, ?string $contactName, ?string $contactRef = null): Conversation
    {
        // Переиспользуем только незакрытый диалог: закрытый остаётся в истории,
        // а новое обращение начинает свежий разговор.
        $active = Conversation::query()
            ->where('channel_id', $channelId)
            ->where('external_chat_id', $externalChatId)
            ->where('status', '!=', ConversationStatus::Closed)
            ->first();

        if ($active !== null) {
            return $active;
        }

        return Conversation::create([
            'channel_id' => $channelId,
            'external_chat_id' => $externalChatId,
            'contact_name' => $contactName,
            'contact_ref' => $contactRef,
            'status' => ConversationStatus::Open,
        ]);
    }

    public function touchLastMessage(Conversation $conversation): void
    {
        $conversation->forceFill(['last_message_at' => now()])->save();
    }

    public function bumpClarificationAttempts(Conversation $conversation): int
    {
        $next = ($conversation->clarification_attempts ?? 0) + 1;
        $conversation->forceFill(['clarification_attempts' => $next])->save();

        return $next;
    }

    public function resetClarificationAttempts(Conversation $conversation): void
    {
        $conversation->forceFill(['clarification_attempts' => 0])->save();
    }

    public function setContactPhone(Conversation $conversation, string $phone): void
    {
        $conversation->forceFill(['contact_phone' => $phone])->save();
    }

    public function setContactName(Conversation $conversation, string $name): void
    {
        $conversation->forceFill(['contact_name' => $name])->save();
    }
}
