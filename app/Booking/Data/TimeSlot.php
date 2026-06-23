<?php

declare(strict_types=1);

namespace App\Booking\Data;

/**
 * Свободный слот записи. start — ISO-8601 дата-время начала.
 */
final readonly class TimeSlot
{
    public function __construct(
        public string $start,
    ) {}
}
