<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Запись доставки рассылки одному получателю: канал, цель (chat id/email), статус
 * и ошибка. Тенант-модель (строгий RLS).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $broadcast_id
 * @property string|null $client_id
 * @property string $channel
 * @property string|null $target
 * @property string $status
 * @property string|null $error
 * @property Carbon $created_at
 */
class BroadcastDelivery extends TenantOwnedModel
{
    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'broadcast_id',
        'client_id',
        'channel',
        'target',
        'status',
        'error',
    ];

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
