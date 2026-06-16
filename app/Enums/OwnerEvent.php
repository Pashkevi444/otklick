<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Событие в диалоге, о котором уведомляем владельца бизнеса.
 */
enum OwnerEvent: string
{
    case NewLead = 'new_lead';
    case NeedsHuman = 'needs_human';
    case Booked = 'booked';
    case Cancelled = 'cancelled';

    public function title(): string
    {
        return match ($this) {
            self::NewLead => 'Новый лид',
            self::NeedsHuman => 'Требуется оператор',
            self::Booked => 'Новая запись',
            self::Cancelled => 'Клиент отменил запись',
        };
    }
}
