<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\ChannelType;
use App\Models\Conversation;
use App\Observers\MessageObserver;
use App\Support\WidgetRealtimeChannel;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * WS-сигнал «в диалоге появилось сообщение» — чтобы кабинет и веб-виджет
 * подтягивали ответы оператора/клиента/бота ЖИВЬЁМ, без поллинга/перезагрузки.
 *
 * По образцу уведомлений (routes/channels.php) несём НЕ текст, а лёгкий пинг с
 * `conversationId`: получатель перезапрашивает свою выдачу авторизованным HTTP
 * (кабинет — `/messages`, виджет — `/poll`), поэтому за WS не утекают данные мимо
 * прав, а изоляция тенантов держится на авторизации приватного канала.
 *
 * Каналы: приватный `tenant.{id}` (кабинет) + публичный канал сессии виджета
 * (если диалог из веб-виджета). ShouldBroadcastNow — без очереди (живой чат).
 */
final class ConversationActivity implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $conversationId,
        public readonly ?string $widgetChannel = null,
    ) {}

    /**
     * Транслирует активность диалога (зовётся из {@see MessageObserver}
     * на создание сообщения, кроме тестовой песочницы).
     */
    public static function dispatchFor(Conversation $conversation): void
    {
        $tenantId = (string) $conversation->getAttribute('tenant_id');
        if ($tenantId === '') {
            return;
        }

        // Для веб-виджета — публичный канал конкретной сессии (имя из channel_id|session).
        $widgetChannel = $conversation->channel?->type === ChannelType::Web && (string) $conversation->external_chat_id !== ''
            ? WidgetRealtimeChannel::name((string) $conversation->channel_id, (string) $conversation->external_chat_id)
            : null;

        self::dispatch($tenantId, (string) $conversation->id, $widgetChannel);
    }

    /**
     * @return array<int, Channel|PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel("tenant.{$this->tenantId}")];

        if ($this->widgetChannel !== null) {
            $channels[] = new Channel($this->widgetChannel);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'conversation.activity';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return ['conversationId' => $this->conversationId];
    }
}
