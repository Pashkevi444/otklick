<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\SandboxRecord;
use App\Tenancy\SandboxScope;
use App\Tenancy\TestContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Применяется к тенант-моделям, строки которых могут создаваться в режиме
 * тестирования бота (диалог, клиент, канал, идентичность, A/B-назначение).
 *
 * - вешает глобальный {@see SandboxScope} (прячет тестовые строки вне теста);
 * - при создании в активном {@see TestContext} регистрирует строку в реестре
 *   {@see SandboxRecord} — её скроет scope и удалит `sandbox:purge`.
 *
 * Зависимые строки (сообщения и т.п.) маркировать не нужно — они уходят каскадом
 * при удалении помеченного «корня» (диалога/клиента).
 */
trait MarksSandbox
{
    public static function bootMarksSandbox(): void
    {
        static::addGlobalScope(new SandboxScope);

        // У моделей с колонкой is_test (напр. clients — для частичного unique-индекса
        // по телефону) проставляем признак ДО вставки, иначе реестр заполняется уже
        // ПОСЛЕ (в created) — поздно для constraint'а.
        static::creating(function (Model $model): void {
            if (app(TestContext::class)->active() && in_array('is_test', $model->getFillable(), true)) {
                $model->setAttribute('is_test', true);
            }
        });

        static::created(function (Model $model): void {
            if (! app(TestContext::class)->active()) {
                return;
            }

            SandboxRecord::query()->create([
                'tenant_id' => $model->getAttribute('tenant_id'),
                'recordable_type' => $model->getTable(),
                'recordable_id' => (string) $model->getKey(),
            ]);
        });
    }

    /**
     * Снять фильтр песочницы (для тест-интерфейса и команды очистки).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithTest(Builder $query): Builder
    {
        return $query->withoutGlobalScope(SandboxScope::class);
    }
}
