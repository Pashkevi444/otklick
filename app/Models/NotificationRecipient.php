<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationChannelType;
use Database\Factories\NotificationRecipientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

/**
 * Получатель уведомлений владельца (email или Telegram), привязанный к бизнесу.
 *
 * @property string $id
 * @property string $tenant_id
 * @property NotificationChannelType $type
 * @property string|null $value
 * @property string|null $label
 * @property bool $is_active
 * @property string|null $link_token
 * @property Carbon|null $verified_at
 */
class NotificationRecipient extends TenantOwnedModel
{
    /** @use HasFactory<NotificationRecipientFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'type',
        'value',
        'label',
        'is_active',
        'link_token',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationChannelType::class,
            'is_active' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Получатель готов принимать уведомления: активен и подтверждён (есть value).
     */
    public function isDeliverable(): bool
    {
        return $this->is_active && $this->value !== null && $this->value !== '';
    }
}
