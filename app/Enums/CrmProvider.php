<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasLabel;

/**
 * CRM-система, в которую бот записывает клиентов. Пока поддерживается только
 * YClients; список расширяется по мере подключения других CRM.
 */
enum CrmProvider: string implements HasLabel
{
    case Yclients = 'yclients';

    public function label(): string
    {
        return match ($this) {
            self::Yclients => 'YClients',
        };
    }
}
