<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Observers\MessageObserver;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Сообщение диалога (входящее от клиента или исходящий ответ).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $conversation_id
 * @property MessageDirection $direction
 * @property string|null $external_message_id
 * @property string|null $text
 * @property array<string, mixed>|null $payload
 * @property MessageStatus $status
 */
#[ObservedBy(MessageObserver::class)]
class Message extends TenantOwnedModel
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'direction',
        'external_message_id',
        'text',
        'payload',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'status' => MessageStatus::class,
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
