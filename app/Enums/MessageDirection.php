<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Направление сообщения относительно бизнеса: входящее от клиента или
 * исходящее (ответ бота / оператора).
 */
enum MessageDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';

    public function label(): string
    {
        return match ($this) {
            self::Inbound => 'Входящее',
            self::Outbound => 'Исходящее',
        };
    }
}
