<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasLabel;

/**
 * Роль стадии в авто-движении воронки: по ней бот двигает сделку при событиях
 * диалога (новый лид / бронь / нужен человек / успех / провал). Кастомные стадии
 * бизнеса роли не имеют (null) — авто-движок их не трогает.
 */
enum DealStageAutomation: string implements HasLabel
{
    case New = 'new';                 // лид только конвертирован в сделку
    case Working = 'working';         // в работе (в т.ч. оформлена будущая запись)
    case NeedsHuman = 'needs_human';  // эскалация — нужен оператор
    case Won = 'won';                 // успех (запись состоялась / сделка выиграна)
    case Lost = 'lost';               // провал (отмена / потеря / спам)

    public function label(): string
    {
        return match ($this) {
            self::New => 'Новый',
            self::Working => 'В работе',
            self::NeedsHuman => 'Нужен человек',
            self::Won => 'Выиграно',
            self::Lost => 'Проиграно',
        };
    }
}
