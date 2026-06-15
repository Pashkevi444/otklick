<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\Contracts\TenantOwned;
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
