<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\PromptBuilder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Промпт бота под нишу (business_type). Глобальная запись (без tenant_id) —
 * правит супер-админ. Хранит НАСТРАИВАЕМУЮ «голову» системного промпта;
 * стандартный «хвост» собирает {@see PromptBuilder}.
 *
 * @property string $id
 * @property string|null $business_type
 * @property string $name
 * @property string $body
 * @property int $sort_order
 */
final class PromptTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'business_type',
        'name',
        'body',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}
