<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Статус доставки сообщения. Входящее фиксируется как received; исходящее
 * переходит sent при успешной отправке в канал или failed при ошибке.
 */
enum MessageStatus: string
{
    case Received = 'received';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Получено',
            self::Sent => 'Отправлено',
            self::Failed => 'Ошибка',
        };
    }
}
