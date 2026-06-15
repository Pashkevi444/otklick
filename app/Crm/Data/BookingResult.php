<?php

declare(strict_types=1);

namespace App\Crm\Data;

/**
 * Результат создания записи в CRM.
 */
final readonly class BookingResult
{
    public function __construct(
        public bool $success,
        public ?string $externalId = null,
        public ?string $message = null,
    ) {}

    public static function ok(string $externalId): self
    {
        return new self(true, $externalId);
    }

    public static function failed(string $message): self
    {
        return new self(false, null, $message);
    }
}
