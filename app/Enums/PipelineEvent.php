<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Событие диалога, двигающее воронку сделки. По нему авто-движок переводит
 * сделку в стадию с соответствующей {@see DealStageAutomation}-ролью.
 */
enum PipelineEvent: string
{
    case NeedsHuman = 'needs_human';  // эскалация — нужен оператор
    case Booked = 'booked';           // оформлена запись/заявка (в работе)
    case Cancelled = 'cancelled';     // клиент отменил
    case Won = 'won';                 // запись состоялась / сделка выиграна
    case Lost = 'lost';               // потеря / спам

    /** В стадию какой роли двигать сделку. */
    public function targetAutomation(): DealStageAutomation
    {
        return match ($this) {
            self::NeedsHuman => DealStageAutomation::NeedsHuman,
            self::Booked => DealStageAutomation::Working,
            self::Cancelled, self::Lost => DealStageAutomation::Lost,
            self::Won => DealStageAutomation::Won,
        };
    }
}
