<?php

declare(strict_types=1);

namespace App\Models;

use App\DTO\PlanFeatures;
use App\Enums\TenantPlan;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Клиент-бизнес (тенант). Корневая сущность изоляции данных.
 *
 * Сама таблица tenants не скоупится по тенанту — она и есть реестр тенантов.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property TenantPlan $plan
 * @property string|null $business_type
 * @property Carbon|null $access_expires_at
 * @property bool $is_blocked
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
        'business_type',
        'access_expires_at',
        'is_blocked',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'plan' => TenantPlan::class,
            'access_expires_at' => 'datetime',
            'is_blocked' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * Эффективные возможности бизнеса: базовые из тарифа + индивидуальные
     * оверрайды супер-админа (settings['overrides']). Источник истины для
     * гейтинга и лимитов на уровне конкретного бизнеса.
     */
    public function features(): PlanFeatures
    {
        $overrides = $this->settings['overrides'] ?? [];

        return ($this->plan ?? TenantPlan::default())->features()->merge(is_array($overrides) ? $overrides : []);
    }

    /**
     * Кабинет доступен, пока бизнес не заблокирован и срок доступа не истёк.
     */
    public function hasActiveAccess(): bool
    {
        if ($this->is_blocked) {
            return false;
        }

        return $this->access_expires_at === null || $this->access_expires_at->isFuture();
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Тип бизнеса из справочника (null = не задан).
     *
     * @return BelongsTo<BusinessType, $this>
     */
    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class, 'business_type', 'key');
    }
}
