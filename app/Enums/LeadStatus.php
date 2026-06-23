<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasLabel;

/**
 * Статус входящего лида: новый → в работе → конвертирован в сделку / отклонён.
 */
enum LeadStatus: string implements HasLabel
{
    case New = 'new';
    case Working = 'working';
    case Converted = 'converted';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Новый',
            self::Working => 'В работе',
            self::Converted => 'В сделке',
            self::Dismissed => 'Отклонён',
        };
    }
}
