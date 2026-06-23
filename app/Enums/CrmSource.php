<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasLabel;

/**
 * Источник CRM-записи (лида/сделки): привёл бот (из диалога) или заведён вручную.
 */
enum CrmSource: string implements HasLabel
{
    case Bot = 'bot';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Bot => 'Из диалога',
            self::Manual => 'Вручную',
        };
    }
}
