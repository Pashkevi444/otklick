<?php

declare(strict_types=1);

namespace App\Shared\Tenancy\Contracts;

use App\Shared\Models\Concerns\BelongsToTenant;

/**
 * Модель, принадлежащая тенанту. Реализуется через трейт
 * {@see BelongsToTenant}.
 */
interface TenantOwned
{
    public function getTenantColumn(): string;

    public function getQualifiedTenantColumn(): string;
}
