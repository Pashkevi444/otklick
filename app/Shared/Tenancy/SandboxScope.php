<?php

declare(strict_types=1);

namespace App\Shared\Tenancy;

use App\Shared\Models\SandboxRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;

/**
 * Глобальный scope, разводящий бизнес-данные и «песочницу» тестовых прогонов
 * (реестр {@see SandboxRecord}):
 *
 *  - обычный режим — прячет тестовые строки (чтобы они не засоряли лиды, базу
 *    клиентов, аналитику и т.п.);
 *  - режим теста ({@see TestContext} активен) — наоборот, оставляет ТОЛЬКО
 *    тестовые строки. Так пайплайн в тесте работает строго в своей песочнице и
 *    не может задеть реальных клиентов/диалоги (например, при вводе телефона,
 *    совпавшего с настоящим клиентом). Реальная конфигурация (база знаний,
 *    воронки, CRM-подключение) не помечается и читается как обычно.
 *
 * Жёсткая чистка тестовых строк — команда `sandbox:purge` (раз в сутки).
 *
 * @implements Scope<Model>
 */
final class SandboxScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $registry = DB::table('sandbox_records')
            ->where('recordable_type', $model->getTable())
            ->select('recordable_id');

        if (app(TestContext::class)->active()) {
            $builder->whereIn($model->getQualifiedKeyName(), $registry);

            return;
        }

        $builder->whereNotIn($model->getQualifiedKeyName(), $registry);
    }
}
