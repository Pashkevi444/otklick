<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChannelType;
use Database\Factories\ChannelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Канал общения тенанта. Креды (токен бота, secret вебхука) шифруются в БД.
 *
 * @property string $id
 * @property string $tenant_id
 * @property ChannelType $type
 * @property string|null $external_id
 * @property array<string, mixed> $credentials
 * @property bool $is_active
 * @property array<string, mixed> $settings
 */
class Channel extends TenantOwnedModel
{
    /** @use HasFactory<ChannelFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'type',
        'external_id',
        'credentials',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'type' => ChannelType::class,
            'credentials' => 'encrypted:array',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function botToken(): ?string
    {
        return $this->credentials['bot_token'] ?? null;
    }

    public function secretToken(): ?string
    {
        return $this->credentials['secret_token'] ?? null;
    }

    /**
     * Значение креда канала по ключу (provider-специфика не утекает в модель).
     */
    public function credential(string $key): ?string
    {
        $value = $this->credentials[$key] ?? null;

        return $value !== null ? (string) $value : null;
    }
}
