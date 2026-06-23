<?php

declare(strict_types=1);

namespace App\Crm\Data;

/**
 * Сведения о филиале/компании из CRM (нормализованные).
 */
final readonly class CrmCompany
{
    public function __construct(
        public string $title,
        public ?string $address = null,
        public ?string $phone = null,
    ) {}
}
