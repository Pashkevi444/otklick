<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChannelType;
use App\Models\Concerns\MarksSandbox;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Нативная идентичность клиента в канале (Telegram chat_id, WhatsApp phone@c.us,
 * VK/MAX user id) — по ней узнаём вернувшегося без спроса контактов. Тенант-модель
 * (строгий RLS). Уникальна по `(tenant_id, channel_type, identity)`.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $client_id
 * @property ChannelType $channel_type
 * @property string $identity
 */
class ClientIdentity extends TenantOwnedModel
{
    use MarksSandbox;

    protected $fillable = [
        'tenant_id',
        'client_id',
        'channel_type',
        'identity',
    ];

    protected function casts(): array
    {
        return [
            'channel_type' => ChannelType::class,
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
