<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Models;

use App\Shared\Models\TenantOwnedModel;
use Database\Factories\KnowledgeEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Запись базы знаний тенанта.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $title
 * @property string $content
 * @property bool $is_published
 * @property list<array{label: string, url: string}> $links
 * @property list<array{path: string, url: string}> $images
 */
class KnowledgeEntry extends TenantOwnedModel
{
    /** @use HasFactory<KnowledgeEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'title',
        'content',
        'is_published',
        'links',
        'images',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'links' => 'array',
            'images' => 'array',
        ];
    }
}
