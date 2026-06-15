<?php

declare(strict_types=1);

namespace App\Channels\Contracts;

use App\Models\Channel;

/**
 * Порт отправки сообщения клиенту через канал. Реализация зависит от типа
 * канала (Telegram, далее WhatsApp). Бизнес-логика работает с портом, не зная
 * деталей конкретного мессенджера.
 *
 * Сейчас реализован только Telegram и биндится напрямую. Когда появится второй
 * канал — добавится резолвер реализации по ChannelType.
 */
interface MessengerGateway
{
    public function send(Channel $channel, string $chatId, string $text): void;
}
