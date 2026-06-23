<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;

/**
 * Определение кастомного поля бизнеса (для лидов или сделок). Значения полей
 * лежат в jsonb `custom` соответствующих записей; здесь — только схема.
 *
 * @property string $id
 * @property string $tenant_id
 * @property CustomFieldEntity $entity
 * @property string $key
 * @property string $label
 * @property CustomFieldType $type
 * @property array<int, string>|null $options
 * @property int $sort_order
 */
final class CustomFieldDef extends TenantOwnedModel
{
    protected $fillable = [
        'tenant_id',
        'entity',
        'key',
        'label',
        'type',
        'options',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'entity' => CustomFieldEntity::class,
            'type' => CustomFieldType::class,
            'options' => 'array',
            'sort_order' => 'integer',
        ];
    }
}
