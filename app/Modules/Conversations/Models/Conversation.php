<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Models;

use App\Modules\Booking\Models\CrmConnection;
use App\Modules\Channels\Models\Channel;
use App\Modules\Clients\Models\Client;
use App\Shared\Enums\ConversationOutcome;
use App\Shared\Enums\ConversationStatus;
use App\Shared\Models\Concerns\MarksSandbox;
use App\Shared\Models\TenantOwnedModel;
use App\Shared\Models\User;
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
 * @property bool $contacts_gate_done
 * @property bool $consent_agreed
 * @property Carbon|null $consent_agreed_at
 * @property string|null $contact_ref
 * @property ConversationStatus $status
 * @property int $clarification_attempts
 * @property Carbon|null $booked_at
 * @property Carbon|null $cancelled_at
 * @property ConversationOutcome|null $outcome_override
 * @property array<string, mixed>|null $booking_state
 * @property array<string, mixed>|null $flow_state
 * @property string|null $crm_record_id
 * @property string|null $crm_connection_id
 * @property string|null $client_id
 * @property string|null $booked_service_id
 * @property string|null $booked_service_title
 * @property int|null $booked_service_price
 * @property Carbon|null $booked_for
 * @property list<int>|null $reminders_sent
 * @property Carbon|null $last_message_at
 * @property Carbon|null $operator_active_at
 * @property int|null $operator_user_id
 */
class Conversation extends TenantOwnedModel
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    use MarksSandbox;

    protected $fillable = [
        'tenant_id',
        'channel_id',
        'external_chat_id',
        'contacts_gate_done',
        'consent_agreed',
        'consent_agreed_at',
        'contact_ref',
        'status',
        'clarification_attempts',
        'booked_at',
        'cancelled_at',
        'outcome_override',
        'booking_state',
        'flow_state',
        'crm_record_id',
        'crm_connection_id',
        'client_id',
        'booked_service_id',
        'booked_service_title',
        'booked_service_price',
        'booked_for',
        'reminders_sent',
        'last_message_at',
        'operator_active_at',
        'operator_user_id',
    ];

    /**
     * Сколько минут без активности оператора держим диалог в ручном режиме,
     * после чего бот снова отвечает (авто-возврат). Команда
     * `conversations:release-idle` доводит флаг до конца и шлёт уведомление.
     */
    public const int OPERATOR_IDLE_MINUTES = 180;

    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'contacts_gate_done' => 'boolean',
            'consent_agreed' => 'boolean',
            'consent_agreed_at' => 'datetime',
            'clarification_attempts' => 'integer',
            'booked_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'outcome_override' => ConversationOutcome::class,
            'booking_state' => 'array',
            'flow_state' => 'array',
            'booked_service_price' => 'integer',
            'booked_for' => 'datetime',
            'reminders_sent' => 'array',
            'last_message_at' => 'datetime',
            'operator_active_at' => 'datetime',
        ];
    }

    /**
     * Диалог сейчас ведёт оператор (перехвачен), а не бот: флаг проставлен и не
     * протух по таймауту бездействия. Бот в этом режиме молчит, отвечает человек.
     */
    public function isOperatorHandling(): bool
    {
        return $this->operator_active_at !== null
            && $this->operator_active_at->gt(now()->subMinutes(self::OPERATOR_IDLE_MINUTES));
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
            // Запись оформлена. Пока время визита впереди — лид «в работе» (клиент
            // может вернуться, перенести или отменить); «Успешный лид» проставляется,
            // когда время визита уже прошло (услуга оказана) либо оно неизвестно.
            if ($this->booked_for !== null && $this->booked_for->isFuture()) {
                return ConversationOutcome::Open;
            }

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
     * Оператор, перехвативший диалог (если перехвачен).
     *
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_user_id');
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
     * Имя/телефон/email лида берём ТОЛЬКО из карточки клиента (нормализация: это
     * атрибуты человека, а не треда). Лид всегда привязан к карточке
     * (ClientService::attachClient). Подгрузи `client`, чтобы не ловить N+1.
     */
    public function displayName(): ?string
    {
        return $this->client?->name;
    }

    public function displayPhone(): ?string
    {
        return $this->client?->phone;
    }

    public function displayEmail(): ?string
    {
        return $this->client?->email;
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
