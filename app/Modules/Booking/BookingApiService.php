<?php

declare(strict_types=1);

namespace App\Modules\Booking;

use App\Modules\Booking\Contracts\BookingApi;
use App\Modules\Booking\Crm\Contracts\CrmGateway;
use App\Modules\Booking\Crm\CrmGatewayResolver;
use App\Modules\Booking\Models\CrmConnection;
use App\Modules\Booking\Repositories\Contracts\CrmConnectionRepositoryInterface;
use App\Modules\Booking\Services\BookingFlow;
use App\Modules\Conversations\Models\Conversation;
use App\Shared\DTO\BotReply;
use App\Shared\Enums\CrmProvider;
use App\Shared\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Фасад модуля «Запись»: реализует {@see BookingApi}, делегируя мастеру записи,
 * репозиторию CRM-подключений и резолверу CRM-стратегий. Имена методов совпадают
 * с внутренними — потребители меняют только тип в конструкторе.
 */
final class BookingApiService implements BookingApi
{
    public function __construct(
        private readonly BookingFlow $booking,
        private readonly CrmConnectionRepositoryInterface $connections,
        private readonly CrmGatewayResolver $gateways,
    ) {}

    public function isAvailable(): bool
    {
        return $this->booking->isAvailable();
    }

    public function start(Tenant $tenant, Conversation $conversation, ?string $supersedesRecordId = null): ?BotReply
    {
        return $this->booking->start($tenant, $conversation, $supersedesRecordId);
    }

    public function advance(Tenant $tenant, Conversation $conversation, string $text): BotReply
    {
        return $this->booking->advance($tenant, $conversation, $text);
    }

    public function interceptIntent(Tenant $tenant, Conversation $conversation, string $text): ?BotReply
    {
        return $this->booking->interceptIntent($tenant, $conversation, $text);
    }

    public function bookingChoiceMenu(Conversation $conversation): ?BotReply
    {
        return $this->booking->bookingChoiceMenu($conversation);
    }

    public function cancelLastBooking(Conversation $conversation): bool
    {
        return $this->booking->cancelLastBooking($conversation);
    }

    public function cancelBookingForConversation(Conversation $conversation): void
    {
        $this->booking->cancelBookingForConversation($conversation);
    }

    public function activeForCurrentTenant(): ?CrmConnection
    {
        return $this->connections->activeForCurrentTenant();
    }

    public function forCurrentTenant(): Collection
    {
        return $this->connections->forCurrentTenant();
    }

    public function crmGatewayFor(CrmProvider $provider): CrmGateway
    {
        return $this->gateways->for($provider);
    }
}
