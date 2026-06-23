<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Crm\Contracts\CrmGateway;
use App\Crm\Data\BookingRequest;
use App\Crm\Data\BookingResult;
use App\Crm\Data\CredentialField;
use App\Crm\Data\CrmCompany;
use App\Crm\Data\CrmService;
use App\Crm\Data\CrmStaff;
use App\Crm\Data\SlotQuery;
use App\Crm\Data\TimeSlot;
use App\Enums\CrmProvider;
use App\Models\CrmConnection;
use RuntimeException;

/**
 * Детерминированная CRM-стратегия для тестов BookingFlow. Поведение
 * настраивается публичными полями: каталог, слоты, сбои.
 */
final class FakeCrmGateway implements CrmGateway
{
    /** @var list<CrmService> */
    public array $services = [];

    /** @var list<CrmStaff> */
    public array $staff = [];

    /** @var list<TimeSlot> */
    public array $slots = [];

    public bool $failBooking = false;

    public bool $failCancel = false;

    public bool $throwOnServices = false;

    /** @var list<BookingRequest> */
    public array $createdBookings = [];

    /** @var list<string> */
    public array $cancelledRecords = [];

    public function provider(): CrmProvider
    {
        return CrmProvider::Yclients;
    }

    public function credentialFields(): array
    {
        return [new CredentialField('company_id', 'ID филиала')];
    }

    public ?CrmCompany $company = null;

    public function verifyConnection(CrmConnection $connection): bool
    {
        return true;
    }

    public function company(CrmConnection $connection): ?CrmCompany
    {
        return $this->company;
    }

    public function services(CrmConnection $connection): array
    {
        if ($this->throwOnServices) {
            throw new RuntimeException('CRM down');
        }

        return $this->services;
    }

    public function staff(CrmConnection $connection): array
    {
        return $this->staff;
    }

    public function availableSlots(CrmConnection $connection, SlotQuery $query): array
    {
        return $this->slots;
    }

    public function createBooking(CrmConnection $connection, BookingRequest $request): BookingResult
    {
        $this->createdBookings[] = $request;

        return $this->failBooking
            ? BookingResult::failed('CRM отказала')
            : BookingResult::ok('rec-1');
    }

    public function cancelBooking(CrmConnection $connection, string $externalId): BookingResult
    {
        $this->cancelledRecords[] = $externalId;

        return $this->failCancel
            ? BookingResult::failed('CRM отказала в отмене')
            : BookingResult::ok($externalId);
    }
}
