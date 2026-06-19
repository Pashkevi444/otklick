<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BroadcastRecurrence;
use App\Enums\BroadcastStatus;
use Database\Factories\BroadcastFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

/**
 * Рассылка по базе клиентов: одно сообщение в выбранные каналы (мессенджеры +
 * почта), разово или по расписанию. Тенант-модель (строгий RLS).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $title
 * @property string $body
 * @property list<string> $channels
 * @property list<string>|null $client_ids
 * @property BroadcastStatus $status
 * @property BroadcastRecurrence $recurrence
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $next_run_at
 * @property Carbon|null $last_run_at
 * @property int $sent_count
 * @property int $failed_count
 * @property int|null $created_by
 */
class Broadcast extends TenantOwnedModel
{
    /** @use HasFactory<BroadcastFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'title',
        'body',
        'channels',
        'client_ids',
        'status',
        'recurrence',
        'scheduled_at',
        'next_run_at',
        'last_run_at',
        'sent_count',
        'failed_count',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'client_ids' => 'array',
            'status' => BroadcastStatus::class,
            'recurrence' => BroadcastRecurrence::class,
            'scheduled_at' => 'datetime',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }
}
