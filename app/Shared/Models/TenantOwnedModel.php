<?php

declare(strict_types=1);

namespace App\Shared\Models;

use App\Shared\Models\Concerns\BelongsToTenant;
use App\Shared\Tenancy\Contracts\TenantOwned;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Базовая модель сущности, принадлежащей тенанту: UUID-ключ, изоляция через
 * {@see BelongsToTenant} и контракт {@see TenantOwned}. Конкретные модели задают
 * только схему (fillable/casts) и связи.
 */
abstract class TenantOwnedModel extends Model implements TenantOwned
{
    use BelongsToTenant;
    use HasUuids;
}
