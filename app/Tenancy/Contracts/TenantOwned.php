<?php

declare(strict_types=1);

namespace App\Tenancy\Contracts;

use App\Models\Concerns\BelongsToTenant;

/**
 * Модель, принадлежащая тенанту. Реализуется через трейт
 * {@see BelongsToTenant}.
 */
interface TenantOwned
{
    public function getTenantColumn(): string;

    public function getQualifiedTenantColumn(): string;
}
