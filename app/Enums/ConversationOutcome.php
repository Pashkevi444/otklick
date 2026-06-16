<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasLabel;

/**
 * Итог по лиду (производный от статуса диалога и факта записи): обработан успешно
 * (запись), потерян (закрыт без записи), ждёт оператора или ещё в работе.
 */
enum ConversationOutcome: string implements HasLabel
{
    case Booked = 'booked';
    case Cancelled = 'cancelled';
    case Lost = 'lost';
    case NeedsHuman = 'needs_human';
    case Open = 'open';

    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Запись',
            self::Cancelled => 'Отменён клиентом',
            self::Lost => 'Потерян',
            self::NeedsHuman => 'Нужен человек',
            self::Open => 'В работе',
        };
    }
}
