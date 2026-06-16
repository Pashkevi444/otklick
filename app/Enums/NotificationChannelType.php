<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasLabel;

/**
 * Канал доставки уведомления владельцу. Расширяется добавлением кейса +
 * соответствующего нотификатора (стратегии).
 */
enum NotificationChannelType: string implements HasLabel
{
    case Email = 'email';
    case Telegram = 'telegram';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Почта',
            self::Telegram => 'Telegram',
        };
    }
}
