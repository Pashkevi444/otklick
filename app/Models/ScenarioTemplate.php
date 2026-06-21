<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Готовый шаблон сценария-воронки. Глобальный (без tenant_id): СУ редактирует,
 * бизнес берёт в кабинете. `business_type` = null — «Общие».
 *
 * @property string $id
 * @property string $key
 * @property string $name
 * @property string $description
 * @property string|null $business_type
 * @property array<int, string> $triggers
 * @property array<string, mixed> $definition
 * @property int $sort_order
 */
class ScenarioTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'key',
        'name',
        'description',
        'business_type',
        'triggers',
        'definition',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'triggers' => 'array',
            'definition' => 'array',
            'sort_order' => 'integer',
        ];
    }
}
