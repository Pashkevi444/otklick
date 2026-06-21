<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Готовый элемент базы знаний. Глобальный (без tenant_id): СУ редактирует, бизнес
 * берёт в кабинете и дозаполняет под себя. `business_type` = null — «Общие».
 *
 * @property string $id
 * @property string $key
 * @property string $title
 * @property string $content
 * @property string|null $business_type
 * @property int $sort_order
 */
class KnowledgeTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'key',
        'title',
        'content',
        'business_type',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
