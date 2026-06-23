<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CrmSource;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Сделка — сущность воронки продаж. Создаётся вручную или конвертацией из лида.
 * `custom` — значения кастомных полей бизнеса.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $client_id
 * @property string $stage_id
 * @property string|null $title
 * @property int|null $value
 * @property int|null $assigned_user_id
 * @property CrmSource $source
 * @property string|null $notes
 * @property Carbon|null $next_action_at
 * @property array<string, mixed>|null $custom
 */
final class Deal extends TenantOwnedModel
{
    protected $fillable = [
        'tenant_id',
        'client_id',
        'stage_id',
        'title',
        'value',
        'assigned_user_id',
        'source',
        'notes',
        'next_action_at',
        'custom',
    ];

    protected function casts(): array
    {
        return [
            'source' => CrmSource::class,
            'value' => 'integer',
            'next_action_at' => 'datetime',
            'custom' => 'array',
        ];
    }

    /**
     * @return BelongsTo<DealStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(DealStage::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
