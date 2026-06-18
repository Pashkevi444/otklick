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
 * @property string|null $contact_email
 * @property bool $contacts_gate_done
 * @property string|null $contact_ref
 * @property ConversationStatus $status
 * @property int $clarification_attempts
 * @property Carbon|null $booked_at
 * @property Carbon|null $cancelled_at
 * @property ConversationOutcome|null $outcome_override
 * @property array<string, mixed>|null $booking_state
 * @property string|null $crm_record_id
 * @property string|null $crm_connection_id
 * @property string|null $client_id
 * @property string|null $booked_service_id
 * @property string|null $booked_service_title
 * @property int|null $booked_service_price
 * @property Carbon|null $booked_for
 * @property list<int>|null $reminders_sent
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
        'contact_email',
        'contacts_gate_done',
        'contact_ref',
        'status',
        'clarification_attempts',
        'booked_at',
        'cancelled_at',
        'outcome_override',
        'booking_state',
        'crm_record_id',
        'crm_connection_id',
        'client_id',
        'booked_service_id',
        'booked_service_title',
        'booked_service_price',
        'booked_for',
        'reminders_sent',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'contacts_gate_done' => 'boolean',
            'clarification_attempts' => 'integer',
            'booked_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'outcome_override' => ConversationOutcome::class,
            'booking_state' => 'array',
            'booked_service_price' => 'integer',
            'booked_for' => 'datetime',
            'reminders_sent' => 'array',
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * Итог по лиду. Ручной итог админа (outcome_override) — в приоритете; иначе
     * выводим автоматически: отмена → успешный (есть booked_at) → по статусу
     * (закрыт = потерянный лид, иначе в работе / нужен человек).
     */
    public function outcome(): ConversationOutcome
    {
        if ($this->outcome_override !== null) {
            return $this->outcome_override;
        }

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
     * CRM-подключение, в которое бот оформил запись (для атрибуции выручки по CRM).
     *
     * @return BelongsTo<CrmConnection, $this>
     */
    public function crmConnection(): BelongsTo
    {
        return $this->belongsTo(CrmConnection::class);
    }

    /**
     * Карточка клиента, к которой привязан лид (по телефону).
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
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
