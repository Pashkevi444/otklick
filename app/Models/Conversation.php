<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationOutcome;
use App\Enums\ConversationStatus;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Диалог с клиентом в рамках канала.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $channel_id
 * @property string $external_chat_id
 * @property string|null $contact_name
 * @property string|null $contact_phone
 * @property string|null $contact_ref
 * @property ConversationStatus $status
 * @property int $clarification_attempts
 * @property Carbon|null $booked_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $last_message_at
 */
class Conversation extends TenantOwnedModel
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'channel_id',
        'external_chat_id',
        'contact_name',
        'contact_phone',
        'contact_ref',
        'status',
        'clarification_attempts',
        'booked_at',
        'cancelled_at',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'clarification_attempts' => 'integer',
            'booked_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * Итог по лиду: запись (есть booked_at), иначе по статусу — потерян (закрыт),
     * нужен человек или в работе.
     */
    public function outcome(): ConversationOutcome
    {
        if ($this->cancelled_at !== null) {
            return ConversationOutcome::Cancelled;
        }

        if ($this->booked_at !== null) {
            return ConversationOutcome::Booked;
        }

        return match ($this->status) {
            ConversationStatus::Closed => ConversationOutcome::Lost,
            ConversationStatus::NeedsHuman => ConversationOutcome::NeedsHuman,
            ConversationStatus::Open => ConversationOutcome::Open,
        };
    }

    /**
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Последнее сообщение диалога. Обычный hasOne с сортировкой (не latestOfMany):
     * в Postgres нет max(uuid), а первичный ключ — UUID.
     *
     * @return HasOne<Message, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latest();
    }
}
