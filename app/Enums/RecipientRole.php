<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasLabel;

/**
 * Роль получателя уведомлений: директор (видит всё, включая недельный дайджест)
 * или сотрудник (обычно — только рабочие события, напр. эскалации).
 */
enum RecipientRole: string implements HasLabel
{
    case Director = 'director';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Director => 'Директор',
            self::Staff => 'Сотрудник',
        };
    }
}
