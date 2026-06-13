<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Глобальный scope: автоматически ограничивает выборку текущим тенантом.
 *
 * Это слой удобства. Когда тенант в контексте не задан (консоль, регистрация
 * тенанта, бутстрап аутентификации) — scope не применяется. Жёсткая гарантия
 * изоляции на уровне БД обеспечивается PostgreSQL Row-Level Security.
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->has()) {
            $builder->where($model->getQualifiedTenantColumn(), $context->id());
        }
    }
}
