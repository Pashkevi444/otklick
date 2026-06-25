<?php

declare(strict_types=1);

namespace App\Modules\Conversations;

use App\Modules\Channels\Models\Channel;
use App\Modules\Conversations\Contracts\ConversationsApi;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Conversations\Repositories\Contracts\ConversationRepositoryInterface;
use App\Modules\Conversations\Repositories\Contracts\MessageRepositoryInterface;
use App\Modules\Conversations\Services\ContactCapture;
use App\Modules\Conversations\Services\IncomingMessageService;
use App\Shared\DTO\IncomingMessage;
use App\Shared\Enums\ConversationStatus;
use App\Shared\Enums\MessageStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Фасад модуля «Диалоги»: реализует {@see ConversationsApi}, делегируя внутренним
 * репозиториям/сервисам. Имена методов совпадают с внутренними — потребители
 * меняют только тип в конструкторе.
 */
final class ConversationsApiService implements ConversationsApi
{
    // Только репозитории (data-слой) в конструкторе. Тяжёлые сервисы
    // IncomingMessageService/ContactCapture тянут Bot/Clients, которые через свои
    // фасады зависят обратно от ConversationsApi → циклическая зависимость в
    // контейнере. Поэтому их резолвим лениво (app()) в момент вызова — цикл рвётся.
    public function __construct(
        private readonly ConversationRepositoryInterface $conversations,
        private readonly MessageRepositoryInterface $messages,
    ) {}

    public function handle(Channel $channel, IncomingMessage $incoming): void
    {
        app(IncomingMessageService::class)->handle($channel, $incoming);
    }

    public function findActiveForChat(string $channelId, string $externalChatId): ?Conversation
    {
        return $this->conversations->findActiveForChat($channelId, $externalChatId);
    }

    public function firstOrCreateForChat(string $channelId, string $externalChatId, ?string $contactName, ?string $contactRef = null): Conversation
    {
        return $this->conversations->firstOrCreateForChat($channelId, $externalChatId, $contactName, $contactRef);
    }

    public function findForCurrentTenant(string $id): ?Conversation
    {
        return $this->conversations->findForCurrentTenant($id);
    }

    public function lastWithCrmRecordForChat(string $channelId, string $externalChatId): ?Conversation
    {
        return $this->conversations->lastWithCrmRecordForChat($channelId, $externalChatId);
    }

    public function activeBookingsForChat(string $channelId, string $externalChatId): Collection
    {
        return $this->conversations->activeBookingsForChat($channelId, $externalChatId);
    }

    public function updateStatus(Conversation $conversation, ConversationStatus $status): void
    {
        $this->conversations->updateStatus($conversation, $status);
    }

    public function markBooked(Conversation $conversation): void
    {
        $this->conversations->markBooked($conversation);
    }

    public function markCancelled(Conversation $conversation): void
    {
        $this->conversations->markCancelled($conversation);
    }

    public function touchLastMessage(Conversation $conversation): void
    {
        $this->conversations->touchLastMessage($conversation);
    }

    public function bumpClarificationAttempts(Conversation $conversation): int
    {
        return $this->conversations->bumpClarificationAttempts($conversation);
    }

    public function resetClarificationAttempts(Conversation $conversation): void
    {
        $this->conversations->resetClarificationAttempts($conversation);
    }

    public function setBookingState(Conversation $conversation, ?array $state): void
    {
        $this->conversations->setBookingState($conversation, $state);
    }

    public function setFlowState(Conversation $conversation, ?array $state): void
    {
        $this->conversations->setFlowState($conversation, $state);
    }

    public function setBookedFor(Conversation $conversation, Carbon $bookedFor): void
    {
        $this->conversations->setBookedFor($conversation, $bookedFor);
    }

    public function setCrmRecordId(Conversation $conversation, ?string $recordId): void
    {
        $this->conversations->setCrmRecordId($conversation, $recordId);
    }

    public function recordBookingValue(Conversation $conversation, string $crmConnectionId, ?string $serviceId, ?string $serviceTitle, ?int $servicePrice): void
    {
        $this->conversations->recordBookingValue($conversation, $crmConnectionId, $serviceId, $serviceTitle, $servicePrice);
    }

    public function setClientId(Conversation $conversation, string $clientId): void
    {
        $this->conversations->setClientId($conversation, $clientId);
    }

    public function clearClientLinks(string $clientId): void
    {
        $this->conversations->clearClientLinks($clientId);
    }

    public function reassignClient(string $fromClientId, string $toClientId): void
    {
        $this->conversations->reassignClient($fromClientId, $toClientId);
    }

    public function recentForConversation(Conversation $conversation, int $limit): Collection
    {
        return $this->messages->recentForConversation($conversation, $limit);
    }

    public function recentForChat(string $channelId, string $externalChatId, int $limit): Collection
    {
        return $this->messages->recentForChat($channelId, $externalChatId, $limit);
    }

    public function allForConversation(Conversation $conversation): Collection
    {
        return $this->messages->allForConversation($conversation);
    }

    public function recordInbound(Conversation $conversation, IncomingMessage $incoming): ?Message
    {
        return $this->messages->recordInbound($conversation, $incoming);
    }

    public function recordOutbound(Conversation $conversation, string $text, MessageStatus $status, array $images = []): Message
    {
        return $this->messages->recordOutbound($conversation, $text, $status, $images);
    }

    public function markStatusById(string $messageId, MessageStatus $status): void
    {
        $this->messages->markStatusById($messageId, $status);
    }

    public function closeCompletedBookingsForCurrentTenant(Carbon $now): int
    {
        return $this->conversations->closeCompletedBookingsForCurrentTenant($now);
    }

    public function upcomingBookedForCurrentTenant(Carbon $from, Carbon $to): Collection
    {
        return $this->conversations->upcomingBookedForCurrentTenant($from, $to);
    }

    public function markReminderSent(Conversation $conversation, int $offsetMinutes): void
    {
        $this->conversations->markReminderSent($conversation, $offsetMinutes);
    }

    public function fromInbound(Conversation $conversation, string $text): void
    {
        app(ContactCapture::class)->fromInbound($conversation, $text);
    }
}
