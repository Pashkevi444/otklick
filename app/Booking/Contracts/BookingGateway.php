<?php

declare(strict_types=1);

namespace App\Booking\Contracts;

use App\Booking\BookingGatewayResolver;
use App\Booking\Data\BookingRequest;
use App\Booking\Data\BookingResult;
use App\Booking\Data\CredentialField;
use App\Booking\Data\CrmCompany;
use App\Booking\Data\CrmService;
use App\Booking\Data\CrmStaff;
use App\Booking\Data\SlotQuery;
use App\Booking\Data\TimeSlot;
use App\Enums\CrmProvider;
use App\Models\CrmConnection;

/**
 * Порт к внешней системе записи/букинга (YClients и т.п.): услуги, мастера,
 * слоты, создание/отмена записи. Каждый провайдер — отдельная реализация; выбор
 * по {@see provider()} через {@see BookingGatewayResolver}. Бот и бизнес-логика
 * работают с этим контрактом, не зная конкретной системы.
 *
 * Это НЕ наша CRM (воронка Лиды/Сделки — `App\Models\Deal`/`Lead`). Под выгрузку
 * нашего пайплайна во внешние CRM (amoCRM/Bitrix) предполагается отдельный порт.
 */
interface BookingGateway
{
    /** Какой провайдер записи обслуживает эта стратегия. */
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
