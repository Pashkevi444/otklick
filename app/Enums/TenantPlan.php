<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasLabel;

/**
 * Тарифный план тенанта (клиента-бизнеса).
 */
enum TenantPlan: string implements HasLabel
{
    case Trial = 'trial';
    case Starter = 'starter';
    case Pro = 'pro';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Пробный',
            self::Starter => 'Стандартный',
            self::Pro => 'Профи',
        };
    }

    /**
     * План по умолчанию при регистрации нового тенанта.
     */
    public static function default(): self
    {
        return self::Trial;
    }
}
