<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * WS-сигнал «клиент печатает» на приватный канал тенанта `tenant.{id}` (тот же, что
 * у уведомлений — изоляция держится на авторизации канала, см. routes/channels.php).
 * Несёт id диалога, чтобы открытая в кабинете переписка показала индикатор только
 * для своего клиента. Без чувствительных данных — оператор и так видит диалоги тенанта.
 *
 * ShouldBroadcastNow — без очереди (индикатор должен появляться мгновенно).
 */
final class ClientTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $conversationId,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->tenantId}")];
    }

    public function broadcastAs(): string
    {
        return 'client.typing';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return ['conversationId' => $this->conversationId];
    }
}
