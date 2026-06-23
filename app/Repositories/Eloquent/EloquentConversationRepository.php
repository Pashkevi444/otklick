<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\ChannelType;
use App\Enums\ConversationOutcome;
use App\Enums\ConversationStatus;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Conversation;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class EloquentConversationRepository implements ConversationRepositoryInterface
{
    public function updateStatus(Conversation $conversation, ConversationStatus $status): void
    {
        $conversation->forceFill(['status' => $status])->save();
    }

    public function markBooked(Conversation $conversation): void
    {
        // Запись оформлена, но диалог НЕ закрываем: лид остаётся «в работе» (Open)
        // с booked_at — клиент может вернуться, перенести/отменить запись, пока
        // время визита не прошло. «Успешный лид» выводится по booked_for
        // (Conversation::outcome), а финально закрывает диалог планировщик после
        // визита (ReconcileBookings).
        $conversation->forceFill([
            'status' => ConversationStatus::Open,
            'booked_at' => now(),
        ])->save();
    }

    public function markCancelled(Conversation $conversation): void
    {
        $conversation->forceFill([
            'status' => ConversationStatus::Closed,
            'cancelled_at' => now(),
        ])->save();
    }

    public function setOutcome(Conversation $conversation, ConversationOutcome $outcome): void
    {
        $status = match ($outcome) {
            ConversationOutcome::Open => ConversationStatus::Open,
            ConversationOutcome::NeedsHuman => ConversationStatus::NeedsHuman,
            default => ConversationStatus::Closed,
        };

        $fields = ['outcome_override' => $outcome, 'status' => $status];

        if ($outcome === ConversationOutcome::Booked && $conversation->booked_at === null) {
            $fields['booked_at'] = now();
        }
        if ($outcome === ConversationOutcome::Cancelled && $conversation->cancelled_at === null) {
            $fields['cancelled_at'] = now();
        }

        $conversation->forceFill($fields)->save();
    }

    public function dashboardStats(): array
    {
        $weekAgo = now()->subDays(7);

        return [
            'leadsToday' => Conversation::query()->where('created_at', '>=', now()->startOfDay())->count(),
            'leadsWeek' => Conversation::query()->where('created_at', '>=', $weekAgo)->count(),
            'bookedWeek' => Conversation::query()->where('booked_at', '>=', $weekAgo)->count(),
        ];
    }

    public function forCurrentTenant(): Collection
    {
        return Conversation::query()
            ->with(['channel', 'latestMessage', 'client'])
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function findForCurrentTenant(string $id): ?Conversation
    {
        return Conversation::query()->with(['channel', 'client'])->find($id);
    }

    public function paginateForCurrentTenant(
        ?string $search,
        ?ConversationStatus $status,
        ?ChannelType $channel,
        string $sort,
        string $direction,
        int $perPage,
    ): LengthAwarePaginator {
        $query = Conversation::query()
            ->with(['channel', 'latestMessage', 'client'])
            ->withCount('messages');

        if ($search !== null && $search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $w) use ($needle): void {
                $w->whereRaw('lower(external_chat_id) like ?', [$needle])
                    // Имя/телефон — в карточке клиента (источник правды).
                    ->orWhereHas('client', fn (Builder $c) => $c->whereRaw('lower(name) like ?', [$needle])->orWhereRaw('lower(phone) like ?', [$needle]))
                    ->orWhereHas('messages', fn (Builder $m) => $m->whereRaw('lower(text) like ?', [$needle]));
            });
        }

        if ($status instanceof ConversationStatus) {
            $query->where('status', $status);
        }

        if ($channel instanceof ChannelType) {
            $query->whereHas('channel', fn (Builder $c) => $c->where('type', $channel->value));
        }

        $dir = $direction === 'asc' ? 'asc' : 'desc';

        if ($sort === 'contact') {
            // Имя — в карточке клиента; сортируем коррелированным подзапросом.
            $query->orderBy(Client::select('name')->whereColumn('clients.id', 'conversations.client_id'), $dir);
        } elseif ($sort === 'messages') {
            $query->orderBy('messages_count', $dir);
        } else {
            $query->orderBy('last_message_at', $dir);
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

        // Новый диалог чата — чистый. Контакты живут в карточке клиента; узнавание
        // вернувшегося — по нативной идентичности канала (ClientService::attachClient),
        // память якорится на клиенте (удалили клиента → бот забыл). $contactName от
        // мессенджеров всегда null (имя собирает контактная форма).
        return Conversation::create([
            'channel_id' => $channelId,
            'external_chat_id' => $externalChatId,
            'contact_ref' => $contactRef,
            'status' => ConversationStatus::Open,
        ]);
    }

    public function clearClientLinks(string $clientId): void
    {
        Conversation::query()->where('client_id', $clientId)->update(['client_id' => null]);
    }

    public function reassignClient(string $fromClientId, string $toClientId): void
    {
        Conversation::query()->where('client_id', $fromClientId)->update(['client_id' => $toClientId]);
    }

    public function findActiveForChat(string $channelId, string $externalChatId): ?Conversation
    {
        return Conversation::query()
            ->where('channel_id', $channelId)
            ->where('external_chat_id', $externalChatId)
            ->where('status', '!=', ConversationStatus::Closed)
            ->first();
    }

    public function setOperator(Conversation $conversation, ?int $operatorUserId): void
    {
        $conversation->forceFill([
            'operator_active_at' => now(),
            'operator_user_id' => $operatorUserId,
        ])->save();
    }

    public function clearOperator(Conversation $conversation): void
    {
        $conversation->forceFill([
            'operator_active_at' => null,
            'operator_user_id' => null,
        ])->save();
    }

    public function touchOperator(Conversation $conversation): void
    {
        $conversation->forceFill(['operator_active_at' => now()])->save();
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function idleOperatorHandled(Carbon $before): Collection
    {
        return Conversation::query()
            ->whereNotNull('operator_active_at')
            ->where('operator_active_at', '<', $before)
            ->get();
    }

    public function touchLastMessage(Conversation $conversation): void
    {
        $conversation->forceFill(['last_message_at' => now()])->save();
    }

    public function closeStaleOpen(Carbon $before): int
    {
        return Conversation::query()
            ->where('status', ConversationStatus::Open)
            ->whereNull('booked_at')
            ->whereNotNull('last_message_at')
            ->where('last_message_at', '<', $before)
            ->update(['status' => ConversationStatus::Closed]);
    }

    public function closeCompletedBookingsForCurrentTenant(Carbon $now): int
    {
        // Запись с CRM-бронью, время визита которой уже прошло, — услуга оказана:
        // закрываем диалог (станет «Успешный лид» по Conversation::outcome).
        return Conversation::query()
            ->where('status', ConversationStatus::Open)
            ->whereNotNull('crm_record_id')
            ->whereNotNull('booked_for')
            ->where('booked_for', '<', $now)
            ->update(['status' => ConversationStatus::Closed]);
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

    public function markContactsGateDone(Conversation $conversation): void
    {
        $conversation->forceFill(['contacts_gate_done' => true])->save();
    }

    public function setClientId(Conversation $conversation, string $clientId): void
    {
        $conversation->forceFill(['client_id' => $clientId])->save();
    }

    public function delete(Conversation $conversation): void
    {
        // Сообщения уходят каскадом (FK conversation_id ON DELETE CASCADE).
        $conversation->delete();
    }

    public function channelTypesForCurrentTenant(): array
    {
        return Channel::query()
            ->whereHas('conversations')
            ->orderBy('type')
            ->distinct()
            ->pluck('type')
            ->map(fn ($type): string => $type instanceof \BackedEnum ? (string) $type->value : (string) $type)
            ->all();
    }

    public function setBookingState(Conversation $conversation, ?array $state): void
    {
        $conversation->forceFill(['booking_state' => $state])->save();
    }

    public function setFlowState(Conversation $conversation, ?array $state): void
    {
        $conversation->forceFill(['flow_state' => $state])->save();
    }

    public function setCrmRecordId(Conversation $conversation, ?string $recordId): void
    {
        $conversation->forceFill(['crm_record_id' => $recordId])->save();
    }

    public function recordBookingValue(
        Conversation $conversation,
        string $crmConnectionId,
        ?string $serviceId,
        ?string $serviceTitle,
        ?int $servicePrice,
    ): void {
        $conversation->forceFill([
            'crm_connection_id' => $crmConnectionId,
            'booked_service_id' => $serviceId,
            'booked_service_title' => $serviceTitle,
            'booked_service_price' => $servicePrice,
        ])->save();
    }

    public function lastWithCrmRecordForChat(string $channelId, string $externalChatId): ?Conversation
    {
        return Conversation::query()
            ->where('channel_id', $channelId)
            ->where('external_chat_id', $externalChatId)
            ->whereNotNull('crm_record_id')
            ->latest()
            ->first();
    }

    public function activeBookingsForChat(string $channelId, string $externalChatId): Collection
    {
        // Предстоящие записи чата (визит ещё не прошёл) — для меню «перенести/
        // отменить/новая» у вернувшегося клиента.
        return Conversation::query()
            ->where('channel_id', $channelId)
            ->where('external_chat_id', $externalChatId)
            ->whereNotNull('crm_record_id')
            ->whereNotNull('booked_for')
            ->where('booked_for', '>', now())
            ->orderBy('booked_for')
            ->get();
    }

    public function setBookedFor(Conversation $conversation, Carbon $bookedFor): void
    {
        $conversation->forceFill(['booked_for' => $bookedFor, 'reminders_sent' => []])->save();
    }

    public function markReminderSent(Conversation $conversation, int $offsetMinutes): void
    {
        $sent = $conversation->reminders_sent ?? [];
        $sent[] = $offsetMinutes;
        $conversation->forceFill(['reminders_sent' => array_values(array_unique($sent))])->save();
    }

    public function upcomingBookedForCurrentTenant(Carbon $from, Carbon $to): Collection
    {
        return Conversation::query()
            ->with(['channel', 'client'])
            ->whereNotNull('crm_record_id')
            ->whereNotNull('booked_for')
            ->whereBetween('booked_for', [$from, $to])
            // Не напоминаем об отменённой или закрытой администратором записи.
            ->whereNull('cancelled_at')
            ->where('status', '!=', ConversationStatus::Closed)
            ->get();
    }
}
