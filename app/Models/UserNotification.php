<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserNotificationType;
use Illuminate\Support\Carbon;

/**
 * In-app уведомление пользователя (колокольчик + бейджи плашек). Принадлежит
 * тенанту (RLS) и конкретному получателю (`user_id`). Создаётся фан-аутом для всех
 * сотрудников тенанта с правом на тип события.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $user_id
 * @property UserNotificationType $type
 * @property string|null $entity_type
 * @property string|null $entity_id
 * @property string $title
 * @property string|null $body
 * @property string|null $url
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 */
final class UserNotification extends TenantOwnedModel
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        'entity_type',
        'entity_id',
        'title',
        'body',
        'url',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => UserNotificationType::class,
            'read_at' => 'datetime',
        ];
    }
}
