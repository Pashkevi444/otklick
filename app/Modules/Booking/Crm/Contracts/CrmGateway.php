<?php

declare(strict_types=1);

namespace App\Modules\Booking\Crm\Contracts;

use App\Modules\Booking\Crm\CrmGatewayResolver;
use App\Modules\Booking\Crm\Data\BookingRequest;
use App\Modules\Booking\Crm\Data\BookingResult;
use App\Modules\Booking\Crm\Data\CredentialField;
use App\Modules\Booking\Crm\Data\CrmCompany;
use App\Modules\Booking\Crm\Data\CrmService;
use App\Modules\Booking\Crm\Data\CrmStaff;
use App\Modules\Booking\Crm\Data\SlotQuery;
use App\Modules\Booking\Crm\Data\TimeSlot;
use App\Modules\Booking\Models\CrmConnection;
use App\Shared\Enums\CrmProvider;

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
     * Сведения о филиале/компании (для базы знаний). null — недоступно.
     */
    public function company(CrmConnection $connection): ?CrmCompany;

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

    /**
     * Отменяет ранее созданную запись по её идентификатору в CRM.
     */
    public function cancelBooking(CrmConnection $connection, string $externalId): BookingResult;
}
