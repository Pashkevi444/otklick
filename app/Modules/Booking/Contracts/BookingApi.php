<?php

declare(strict_types=1);

namespace App\Modules\Booking\Contracts;

use App\Modules\Booking\BookingApiService;
use App\Modules\Booking\Crm\Contracts\CrmGateway;
use App\Modules\Booking\Models\CrmConnection;
use App\Modules\Conversations\Models\Conversation;
use App\Shared\DTO\BotReply;
use App\Shared\Enums\CrmProvider;
use App\Shared\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Публичный контракт модуля «Запись» — дверь для других модулей. Мастер записи
 * (BookingFlow) дёргают бот/диалоги/сценарии; CRM-подключение и порт CRM читают
 * Knowledge (синк знаний из CRM), Notifications, Analytics. Внутренний YClients-
 * адаптер и резолвер — приватны; наружу отдаётся только интерфейс {@see CrmGateway}.
 * Реализация — {@see BookingApiService}.
 */
interface BookingApi
{
    // --- Мастер записи (Bot / Conversations / Flows) ---
    public function isAvailable(): bool;

    public function start(Tenant $tenant, Conversation $conversation, ?string $supersedesRecordId = null): ?BotReply;

    public function advance(Tenant $tenant, Conversation $conversation, string $text): BotReply;

    public function interceptIntent(Tenant $tenant, Conversation $conversation, string $text): ?BotReply;

    public function bookingChoiceMenu(Conversation $conversation): ?BotReply;

    public function cancelLastBooking(Conversation $conversation): bool;

    public function cancelBookingForConversation(Conversation $conversation): void;

    // --- CRM-подключение тенанта (Knowledge / Notifications / Analytics) ---
    public function activeForCurrentTenant(): ?CrmConnection;

    /** @return Collection<int, CrmConnection> */
    public function forCurrentTenant(): Collection;

    // --- Порт CRM: стратегия по провайдеру (Knowledge — синк знаний из CRM) ---
    public function crmGatewayFor(CrmProvider $provider): CrmGateway;
}
