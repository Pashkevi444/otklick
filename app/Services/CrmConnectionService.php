<?php

declare(strict_types=1);

namespace App\Services;

use App\Crm\CrmGatewayResolver;
use App\Crm\Data\BookingRequest;
use App\Crm\Data\BookingResult;
use App\Crm\Data\CredentialField;
use App\Crm\Data\CrmService;
use App\Crm\Data\CrmStaff;
use App\Crm\Data\SlotQuery;
use App\Crm\Data\TimeSlot;
use App\DTO\NewCrmConnectionData;
use App\Enums\CrmProvider;
use App\Models\CrmConnection;
use App\Repositories\Contracts\CrmConnectionRepositoryInterface;

/**
 * Бизнес-логика подключения тенанта к CRM. Один тенант — одно подключение на
 * провайдера (переподключение заменяет прежнее).
 */
final readonly class CrmConnectionService
{
    public function __construct(
        private CrmConnectionRepositoryInterface $connections,
        private CrmGatewayResolver $gateways,
    ) {}

    /**
     * Подключает тенанта к CRM. Провайдер-агностично: набор кредов диктует
     * стратегия провайдера. Переподключение заменяет прежнее подключение.
     *
     * @param  array<string, string>  $credentials
     * @param  array<string, mixed>  $settings
     */
    public function connect(string $tenantId, CrmProvider $provider, array $credentials, array $settings = []): CrmConnection
    {
        $existing = $this->connections->findByProviderForCurrentTenant($provider);

        if ($existing !== null) {
            $this->connections->delete($existing);
        }

        return $this->connections->create(new NewCrmConnectionData(
            tenantId: $tenantId,
            provider: $provider,
            credentials: $credentials,
            settings: $settings,
        ));
    }

    /**
     * Отключает тенанта от провайдера (удаляет подключение текущего тенанта).
     * Идемпотентно: если подключения нет — ничего не делает.
     */
    public function disconnect(CrmProvider $provider): void
    {
        $existing = $this->connections->findByProviderForCurrentTenant($provider);

        if ($existing !== null) {
            $this->connections->delete($existing);
        }
    }

    /**
     * Поля кредов, нужные для подключения провайдера (для формы и валидации).
     *
     * @return list<CredentialField>
     */
    public function credentialFields(CrmProvider $provider): array
    {
        return $this->gateways->for($provider)->credentialFields();
    }

    public function verify(CrmConnection $connection): bool
    {
        return $this->gateways->for($connection->provider)->verifyConnection($connection);
    }

    /**
     * @return list<CrmService>
     */
    public function services(CrmConnection $connection): array
    {
        return $this->gateways->for($connection->provider)->services($connection);
    }

    /**
     * @return list<CrmStaff>
     */
    public function staff(CrmConnection $connection): array
    {
        return $this->gateways->for($connection->provider)->staff($connection);
    }

    /**
     * @return list<TimeSlot>
     */
    public function availableSlots(CrmConnection $connection, SlotQuery $query): array
    {
        return $this->gateways->for($connection->provider)->availableSlots($connection, $query);
    }

    public function createBooking(CrmConnection $connection, BookingRequest $request): BookingResult
    {
        return $this->gateways->for($connection->provider)->createBooking($connection, $request);
    }
}
