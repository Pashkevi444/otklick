<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Тарифный план тенанта (клиента-бизнеса).
 */
enum TenantPlan: string
{
    case Trial = 'trial';
    case Starter = 'starter';
    case Pro = 'pro';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Пробный',
            self::Starter => 'Стартовый',
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
