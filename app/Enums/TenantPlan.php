<?php

declare(strict_types=1);

namespace App\Enums;

use App\DTO\PlanFeatures;
use App\Enums\Contracts\HasLabel;

/**
 * Тарифный план тенанта (клиента-бизнеса).
 *
 * - Trial (Пробный)             — бесплатный пробный период; возможности уровня «Стандарт».
 * - Standard (Стандарт)         — базовый платный тариф.
 * - Max (Макс)                  — премиум: всё включено, удвоенные лимиты.
 * - Individual (Индивидуальный) — корпоративный: всё + кратно бо́льшие лимиты, по договору.
 */
enum TenantPlan: string implements HasLabel
{
    case Trial = 'trial';
    case Standard = 'standard';
    case Max = 'max';
    case Individual = 'individual';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Пробный',
            self::Standard => 'Стандарт',
            self::Max => 'Макс',
            self::Individual => 'Индивидуальный',
        };
    }

    /**
     * Цена в рублях за месяц. Для Пробного — 0 (бесплатно).
     */
    public function priceRub(): int
    {
        return match ($this) {
            self::Trial => 0,
            self::Standard => 9900,
            self::Max => 14900,
            self::Individual => 4000000,
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
            // Индивидуальный — всё включено и кратно бо́льшие лимиты.
            self::Individual => new PlanFeatures(
                maxOperators: 20,
                crm: true,
                analytics: true,
                broadcasts: true,
                clientBase: true,
                allChannels: true,
                webWidget: true,
                maxNotifyEmail: 20,
                maxNotifyTelegram: 80,
                reminders: true,
            ),
            // Макс — всё включено, удвоенные лимиты относительно прежнего премиума.
            self::Max => new PlanFeatures(
                maxOperators: 10,
                crm: true,
                analytics: true,
                broadcasts: true,
                clientBase: true,
                allChannels: true,
                webWidget: true,
                maxNotifyEmail: 10,
                maxNotifyTelegram: 40,
                reminders: true,
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
                reminders: false,
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
