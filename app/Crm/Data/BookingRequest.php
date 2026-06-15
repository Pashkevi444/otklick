<?php

declare(strict_types=1);

namespace App\Crm\Data;

/**
 * Запрос на создание записи клиента в CRM.
 */
final readonly class BookingRequest
{
    public function __construct(
        public string $serviceId,
        public string $staffId,
        public string $start,
        public string $clientName,
        public string $clientPhone,
        public ?string $comment = null,
    ) {}
}
