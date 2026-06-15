<?php

declare(strict_types=1);

namespace App\Crm\Data;

/**
 * Сотрудник/мастер CRM (нормализованный).
 */
final readonly class CrmStaff
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $specialization = null,
    ) {}
}
