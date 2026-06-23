<?php

declare(strict_types=1);

namespace App\Crm\Data;

/**
 * Услуга CRM (нормализованная, не зависит от конкретной системы).
 */
final readonly class CrmService
{
    public function __construct(
        public string $id,
        public string $title,
        public ?int $price = null,
        public ?int $durationMinutes = null,
    ) {}
}
