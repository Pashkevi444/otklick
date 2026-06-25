<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * WS-сигнал «вышла новость/обновление от супер-админа» на ПУБЛИЧНЫЙ канал
 * `announcements` (новости общие для всех тенантов). Несёт лишь пинг — кабинет по
 * нему перезапрашивает свой бейдж непрочитанных (per-tenant) частичным Inertia-
 * релоадом, без перезагрузки страницы и без утечки данных за WS.
 *
 * ShouldBroadcastNow — без очереди.
 */
final class AnnouncementPublished implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('announcements')];
    }

    public function broadcastAs(): string
    {
        return 'announcement.published';
    }
}
