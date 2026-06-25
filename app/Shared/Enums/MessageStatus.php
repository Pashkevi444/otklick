<?php

declare(strict_types=1);

namespace App\Shared\Enums;

use App\Shared\Enums\Contracts\HasLabel;

/**
 * Статус доставки сообщения. Входящее фиксируется как received; исходящее
 * переходит sent при успешной отправке в канал, queued — поставлено в очередь на
 * повторную доставку (отправка сорвалась), failed — доставить так и не удалось.
 */
enum MessageStatus: string implements HasLabel
{
    case Received = 'received';
    case Sent = 'sent';
    case Failed = 'failed';
    case Queued = 'queued';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Получено',
            self::Sent => 'Отправлено',
            self::Failed => 'Ошибка',
            self::Queued => 'В очереди на отправку',
        };
    }
}
