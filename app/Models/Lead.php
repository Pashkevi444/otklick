<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CrmSource;
use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Лид — входящее обращение (из диалога бота по контактной форме или вручную).
 * Конвертируется в сделку (`deal_id`). `custom` — кастомные поля бизнеса.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $client_id
 * @property string|null $conversation_id
 * @property string|null $deal_id
 * @property LeadStatus $status
 * @property CrmSource $source
 * @property string|null $title
 * @property string|null $notes
 * @property array<string, mixed>|null $custom
 */
final class Lead extends TenantOwnedModel
{
    protected $fillable = [
        'tenant_id',
        'client_id',
        'conversation_id',
        'deal_id',
        'status',
        'source',
        'title',
        'notes',
        'custom',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'source' => CrmSource::class,
            'custom' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }
}
