<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantPlan;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Клиент-бизнес (тенант). Корневая сущность изоляции данных.
 *
 * Сама таблица tenants не скоупится по тенанту — она и есть реестр тенантов.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property TenantPlan $plan
 * @property array<string, mixed> $settings
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'plan',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'plan' => TenantPlan::class,
            'settings' => 'array',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
