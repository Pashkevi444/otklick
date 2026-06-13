<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\TenantPlan;

/**
 * Данные для создания нового тенанта. Переносятся между сервисом и репозиторием.
 */
final readonly class NewTenantData
{
    public function __construct(
        public string $name,
        public string $slug,
        public TenantPlan $plan,
        /** @var array<string, mixed> */
        public array $settings = [],
    ) {}
}
