<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * WS-пинг «у тебя обновились уведомления» на приватный канал тенанта. Несёт НЕ
 * данные (чтобы за сокет не утекало мимо прав сотрудника), а сигнал — клиент по
 * нему перезапрашивает свою персональную выдачу через `cabinet.bell.feed`.
 */
final class NotificationsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly string $tenantId) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->tenantId}")];
    }

    public function broadcastAs(): string
    {
        return 'notifications.updated';
    }
}
