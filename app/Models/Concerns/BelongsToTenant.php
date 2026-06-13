<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Применяется к любой модели, принадлежащей тенанту.
 *
 * - вешает глобальный {@see TenantScope} (фильтр по текущему тенанту);
 * - при создании автоматически проставляет tenant_id из контекста.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            if ($model->getAttribute($model->getTenantColumn()) !== null) {
                return;
            }

            $context = app(TenantContext::class);

            if ($context->has()) {
                $model->setAttribute($model->getTenantColumn(), $context->id());
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, $this->getTenantColumn());
    }

    public function getTenantColumn(): string
    {
        return 'tenant_id';
    }

    public function getQualifiedTenantColumn(): string
    {
        return $this->qualifyColumn($this->getTenantColumn());
    }
}
