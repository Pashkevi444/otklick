<?php

declare(strict_types=1);

namespace App\Crm\Contracts;

use App\Crm\CrmGatewayResolver;
use App\Crm\Data\BookingRequest;
use App\Crm\Data\BookingResult;
use App\Crm\Data\CredentialField;
use App\Crm\Data\CrmService;
use App\Crm\Data\CrmStaff;
use App\Crm\Data\SlotQuery;
use App\Crm\Data\TimeSlot;
use App\Enums\CrmProvider;
use App\Models\CrmConnection;

/**
 * Стратегия интеграции с CRM. Каждая CRM — отдельная реализация; выбор по
 * {@see provider()} через {@see CrmGatewayResolver}. Бизнес-логика
 * (и бот) работают с этим контрактом, не зная конкретной системы.
 */
interface CrmGateway
{
    /** Какой CRM-провайдер обслуживает эта стратегия. */
    public function provider(): CrmProvider;

    /**
     * Поля кредов, которые нужны для подключения (для валидации и формы).
     *
     * @return list<CredentialField>
     */
    public function credentialFields(): array;

    public function verifyConnection(CrmConnection $connection): bool;

    /**
     * @return list<CrmService>
     */
    public function services(CrmConnection $connection): array;

    /**
     * @return list<CrmStaff>
     */
    public function staff(CrmConnection $connection): array;

    /**
     * @return list<TimeSlot>
     */
    public function availableSlots(CrmConnection $connection, SlotQuery $query): array;

    public function createBooking(CrmConnection $connection, BookingRequest $request): BookingResult;
}
