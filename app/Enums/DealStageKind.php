<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasLabel;

/**
 * Тип стадии воронки: рабочая (сделка в процессе), выиграно или проиграно.
 */
enum DealStageKind: string implements HasLabel
{
    case Active = 'active';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'В работе',
            self::Won => 'Выиграно',
            self::Lost => 'Проиграно',
        };
    }
}
