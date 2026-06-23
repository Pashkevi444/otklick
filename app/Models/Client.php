<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\MarksSandbox;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Клиент бизнеса — единая карточка, к которой привязываются лиды (диалоги).
 * Идентичность по телефону в пределах тенанта; строгий RLS.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $telegram_username
 * @property string|null $first_channel_type
 * @property Carbon|null $first_seen_at
 * @property Carbon|null $last_seen_at
 * @property string|null $summary
 * @property Carbon|null $summary_generated_at
 * @property string|null $notes
 * @property bool $marketing_opt_out
 * @property bool $is_test
 * @property Carbon|null $banned_at
 */
class Client extends TenantOwnedModel
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    use MarksSandbox;

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
        'telegram_username',
        'first_channel_type',
        'first_seen_at',
        'last_seen_at',
        'summary',
        'summary_generated_at',
        'notes',
        'marketing_opt_out',
        'is_test',
        'banned_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'summary_generated_at' => 'datetime',
            'marketing_opt_out' => 'boolean',
            'is_test' => 'boolean',
            'banned_at' => 'datetime',
        ];
    }

    /** Заблокирован ли клиент бизнесом (бот ему не отвечает по существу). */
    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Нативные идентичности клиента по каналам (для узнавания вернувшегося).
     *
     * @return HasMany<ClientIdentity, $this>
     */
    public function identities(): HasMany
    {
        return $this->hasMany(ClientIdentity::class);
    }
}
