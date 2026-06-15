<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\Contracts\TenantOwned;
use Database\Factories\KnowledgeEntryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Запись базы знаний тенанта.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $title
 * @property string $content
 * @property bool $is_published
 */
class KnowledgeEntry extends Model implements TenantOwned
{
    /** @use HasFactory<KnowledgeEntryFactory> */
    use BelongsToTenant, HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'title',
        'content',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }
}
