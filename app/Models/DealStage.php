<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DealStageAutomation;
use App\Enums\DealStageKind;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Стадия воронки продаж (per-tenant, настраиваемая). Сделки двигаются по стадиям.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property DealStageKind $kind
 * @property DealStageAutomation|null $automation
 * @property int $sort_order
 * @property string|null $color
 */
final class DealStage extends TenantOwnedModel
{
    protected $fillable = [
        'tenant_id',
        'name',
        'kind',
        'automation',
        'sort_order',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'kind' => DealStageKind::class,
            'automation' => DealStageAutomation::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
