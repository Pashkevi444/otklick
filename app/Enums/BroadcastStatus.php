<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasLabel;

/**
 * Состояние рассылки по базе клиентов.
 */
enum BroadcastStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Sent = 'sent';
    case Canceled = 'canceled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Scheduled => 'Запланирована',
            self::Sending => 'Отправляется',
            self::Sent => 'Отправлена',
            self::Canceled => 'Отменена',
            self::Failed => 'Ошибка',
        };
    }
}
