<?php

declare(strict_types=1);

namespace App\Channels\Contracts;

use App\DTO\ReplyKeyboard;
use App\Models\Channel;

/**
 * Порт отправки сообщения клиенту через канал. Реализация зависит от типа
 * канала (Telegram, далее WhatsApp). Бизнес-логика работает с портом, не зная
 * деталей конкретного мессенджера.
 *
 * $keyboard — необязательная клавиатура-подсказка (кликабельные варианты в
 * мастере записи). Каждый канал рендерит её в свой формат (Telegram/VK —
 * reply-кнопки, MAX — inline-кнопки с callback); null — обычный текст.
 */
interface MessengerGateway
{
    public function send(Channel $channel, string $chatId, string $text, ?ReplyKeyboard $keyboard = null): void;
}
