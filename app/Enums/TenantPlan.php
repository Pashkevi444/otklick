<?php

declare(strict_types=1);

namespace App\Enums;

use App\DTO\PlanFeatures;
use App\Enums\Contracts\HasLabel;

/**
 * Тарифный план тенанта (клиента-бизнеса).
 *
 * - Trial (Пробный)     — бесплатный пробный период; возможности уровня «Стандарт».
 * - Standard (Стандарт) — базовый платный тариф.
 * - Max (Макс)          — премиум; продаётся по договорённости (назначает супер-админ).
 */
enum TenantPlan: string implements HasLabel
{
    case Trial = 'trial';
    case Standard = 'standard';
    case Max = 'max';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Пробный',
            self::Standard => 'Стандарт',
            self::Max => 'Макс',
        };
    }

    /**
     * Уровень возможностей. Пробный наследует возможности «Стандарта».
     */
    public function tier(): self
    {
        return $this === self::Trial ? self::Standard : $this;
    }

    /**
     * Что доступно в кабинете на этом тарифе.
     */
    public function features(): PlanFeatures
    {
        return match ($this->tier()) {
            self::Max => new PlanFeatures(
                maxOperators: 5,
                crm: true,
                analytics: true,
                broadcasts: true,
                clientBase: true,
                allChannels: true,
                webWidget: true,
                maxNotifyEmail: 5,
                maxNotifyTelegram: 20,
            ),
            default => new PlanFeatures(
                maxOperators: 2,
                crm: false,
                analytics: false,
                broadcasts: false,
                clientBase: false,
                allChannels: false,
                webWidget: true,
                maxNotifyEmail: 1,
                maxNotifyTelegram: 4,
            ),
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
