<?php

declare(strict_types=1);

namespace App\Events;

use App\Support\WidgetRealtimeChannel;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * WS-сигнал «оператор печатает» на ПУБЛИЧНЫЙ канал веб-виджета (имя выводится из
 * сессии посетителя, см. {@see WidgetRealtimeChannel}). Несёт только
 * факт «печатает» — виджет показывает индикатор и сам гасит его по таймауту.
 *
 * ShouldBroadcastNow — без очереди (индикатор должен появляться мгновенно).
 */
final class OperatorTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly string $channelName) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel($this->channelName)];
    }

    public function broadcastAs(): string
    {
        return 'operator.typing';
    }
}
