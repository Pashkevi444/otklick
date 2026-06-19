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
    case Vk = 'vk';
    case Max = 'max';
    case WhatsApp = 'whatsapp';
    case Web = 'web';

    public function label(): string
    {
        return match ($this) {
            self::Telegram => 'Telegram',
            self::Vk => 'ВКонтакте',
            self::Max => 'MAX',
            self::WhatsApp => 'WhatsApp',
            self::Web => 'Веб-виджет',
        };
    }

    /**
     * Каналы-мессенджеры, которые сервер опрашивает long polling'ом
     * (приём входящих). Веб-виджет получает сообщения по HTTP, его тут нет.
     *
     * @return list<self>
     */
    public static function pollable(): array
    {
        return [self::Telegram, self::Vk, self::Max, self::WhatsApp];
    }
}
