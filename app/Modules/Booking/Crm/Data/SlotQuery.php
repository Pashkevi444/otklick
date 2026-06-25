<?php

declare(strict_types=1);

namespace App\Modules\Booking\Crm\Data;

/**
 * Запрос свободных слотов: дата (YYYY-MM-DD) для мастера и (опц.) услуги.
 */
final readonly class SlotQuery
{
    public function __construct(
        public string $staffId,
        public string $date,
        public ?string $serviceId = null,
    ) {}
}
