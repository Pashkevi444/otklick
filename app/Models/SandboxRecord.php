<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Запись реестра «песочницы»: помечает строку основной таблицы как тестовую
 * (создана в режиме тестирования бота). Источник истины для {@see
 * \App\Tenancy\SandboxScope} (скрытие из бизнес-выборок) и для команды
 * `sandbox:purge` (удаление раз в сутки).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $recordable_type имя таблицы помеченной строки
 * @property string $recordable_id
 */
class SandboxRecord extends TenantOwnedModel
{
    protected $fillable = [
        'tenant_id',
        'recordable_type',
        'recordable_id',
    ];
}
