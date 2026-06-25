<?php

declare(strict_types=1);

namespace App\Shared\Enums;

use App\Shared\Enums\Contracts\HasLabel;

/**
 * Направление сообщения относительно бизнеса: входящее от клиента или
 * исходящее (ответ бота / оператора).
 */
enum MessageDirection: string implements HasLabel
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
