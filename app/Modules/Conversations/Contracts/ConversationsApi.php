<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Contracts;

use App\Modules\Channels\Models\Channel;
use App\Modules\Conversations\ConversationsApiService;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Shared\DTO\IncomingMessage;
use App\Shared\Enums\ConversationStatus;
use App\Shared\Enums\MessageStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Публичный контракт модуля «Диалоги» (инбокс) — единственная дверь для других
 * модулей. Поверхность широкая: Conversations — центральный модуль, его состоянием
 * диалога/сообщений оперируют запись (Booking), бот (Bot), сценарии (Flows),
 * клиенты (Clients), песочница (Sandbox), приём из каналов (Channels). Внутренние
 * репозитории/сервисы — приватны. Реализация — {@see ConversationsApiService}.
 */
interface ConversationsApi
{
    // --- Приём входящего из канала (Channels) ---
    public function handle(Channel $channel, IncomingMessage $incoming): void;

    // --- Диалог: поиск/создание ---
    public function findActiveForChat(string $channelId, string $externalChatId): ?Conversation;

    public function firstOrCreateForChat(string $channelId, string $externalChatId, ?string $contactName, ?string $contactRef = null): Conversation;

    public function findForCurrentTenant(string $id): ?Conversation;

    public function lastWithCrmRecordForChat(string $channelId, string $externalChatId): ?Conversation;

    /** @return Collection<int, Conversation> */
    public function activeBookingsForChat(string $channelId, string $externalChatId): Collection;

    // --- Диалог: статус/состояние ---
    public function updateStatus(Conversation $conversation, ConversationStatus $status): void;

    public function markBooked(Conversation $conversation): void;

    public function markCancelled(Conversation $conversation): void;

    public function touchLastMessage(Conversation $conversation): void;

    public function bumpClarificationAttempts(Conversation $conversation): int;

    public function resetClarificationAttempts(Conversation $conversation): void;

    /** @param  array<string, mixed>|null  $state */
    public function setBookingState(Conversation $conversation, ?array $state): void;

    /** @param  array<string, mixed>|null  $state */
    public function setFlowState(Conversation $conversation, ?array $state): void;

    public function setBookedFor(Conversation $conversation, Carbon $bookedFor): void;

    public function setCrmRecordId(Conversation $conversation, ?string $recordId): void;

    public function recordBookingValue(Conversation $conversation, string $crmConnectionId, ?string $serviceId, ?string $serviceTitle, ?int $servicePrice): void;

    // --- Связь с клиентом (Clients) ---
    public function setClientId(Conversation $conversation, string $clientId): void;

    public function clearClientLinks(string $clientId): void;

    public function reassignClient(string $fromClientId, string $toClientId): void;

    // --- Сообщения ---
    /** @return Collection<int, Message> */
    public function recentForConversation(Conversation $conversation, int $limit): Collection;

    /** @return Collection<int, Message> */
    public function recentForChat(string $channelId, string $externalChatId, int $limit): Collection;

    /** @return Collection<int, Message> */
    public function allForConversation(Conversation $conversation): Collection;

    public function recordInbound(Conversation $conversation, IncomingMessage $incoming): ?Message;

    /** @param  list<string>  $images */
    public function recordOutbound(Conversation $conversation, string $text, MessageStatus $status, array $images = []): Message;

    public function markStatusById(string $messageId, MessageStatus $status): void;

    // --- Напоминания/сверка записей (Booking-команды/джобы) ---
    public function closeCompletedBookingsForCurrentTenant(Carbon $now): int;

    /** @return Collection<int, Conversation> */
    public function upcomingBookedForCurrentTenant(Carbon $from, Carbon $to): Collection;

    public function markReminderSent(Conversation $conversation, int $offsetMinutes): void;

    // --- Захват контакта из текста ---
    public function fromInbound(Conversation $conversation, string $text): void;
}
