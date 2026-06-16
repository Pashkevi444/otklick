<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasLabel;

/**
 * Итог по лиду — универсально для любого бизнеса (не только запись на услугу).
 * Выводится автоматически из состояния диалога, но администратор может выставить
 * любой вручную (см. Conversation::outcome / outcome_override).
 */
enum ConversationOutcome: string implements HasLabel
{
    case Booked = 'booked';        // успешно обработан (запись/заявка/заказ)
    case Cancelled = 'cancelled';  // клиент отменил
    case Lost = 'lost';            // закрыт без результата
    case NeedsHuman = 'needs_human';
    case Open = 'open';
    case Spam = 'spam';            // нерелевантное обращение (ставит админ)

    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Успешный лид',
            self::Cancelled => 'Отменён клиентом',
            self::Lost => 'Потерянный лид',
            self::NeedsHuman => 'Нужен человек',
            self::Open => 'В работе',
            self::Spam => 'Спам / нерелевантный',
        };
    }

    /** Итог означает закрытый диалог (не «в работе» и не «нужен человек»). */
    public function isClosed(): bool
    {
        return match ($this) {
            self::Booked, self::Cancelled, self::Lost, self::Spam => true,
            self::NeedsHuman, self::Open => false,
        };
    }
}
