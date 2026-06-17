<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CrmKnowledgeCategory;

/**
 * Нередактируемая запись базы знаний, выгруженная из CRM (услуга/мастер/филиал).
 *
 * @property string $id
 * @property string $tenant_id
 * @property CrmKnowledgeCategory $category
 * @property string|null $external_id
 * @property string $title
 * @property string $content
 * @property array<string, mixed>|null $meta
 */
class CrmKnowledgeEntry extends TenantOwnedModel
{
    protected $fillable = [
        'tenant_id',
        'category',
        'external_id',
        'title',
        'content',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'category' => CrmKnowledgeCategory::class,
            'meta' => 'array',
        ];
    }
}
