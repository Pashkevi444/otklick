<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GridEntity;

/**
 * Сохранённый вид универсального грида (личный, на пользователя). `config` —
 * выбранные колонки/фильтры/сортировка.
 *
 * @property string $id
 * @property string $tenant_id
 * @property int $user_id
 * @property GridEntity $entity
 * @property string $name
 * @property array<string, mixed> $config
 */
final class GridView extends TenantOwnedModel
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'entity',
        'name',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'entity' => GridEntity::class,
            'config' => 'array',
        ];
    }
}
