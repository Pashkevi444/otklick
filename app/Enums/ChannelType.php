<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasLabel;

/**
 * Тип канала общения, через который бизнес получает обращения клиентов.
 */
enum ChannelType: string implements HasLabel
{
    case Telegram = 'telegram';
    case WhatsApp = 'whatsapp';
    case Web = 'web';

    public function label(): string
    {
        return match ($this) {
            self::Telegram => 'Telegram',
            self::WhatsApp => 'WhatsApp',
            self::Web => 'Веб-виджет',
        };
    }
}
