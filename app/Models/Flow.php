<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FlowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Сценарий-воронка (no-code логика бота). Граф в `definition`:
 * {start: nodeId, nodes: {id: {type, text, action, options:[{label,next}]}}}.
 * Запуск — по фразам из `triggers`. Тенант-модель (строгий RLS).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property bool $is_active
 * @property list<string> $triggers
 * @property array<string, mixed> $definition
 */
class Flow extends TenantOwnedModel
{
    /** @use HasFactory<FlowFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'is_active',
        'triggers',
        'definition',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'triggers' => 'array',
            'definition' => 'array',
        ];
    }
}
