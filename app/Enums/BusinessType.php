<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\FlowTemplates;

/**
 * Тип бизнеса — реестр для группировки готовых шаблонов сценариев (и в будущем
 * для подбора шаблонов/настроек под нишу). «Общие» сюда НЕ входит: это дефолтная
 * категория (шаблоны без типа), а не тип бизнеса. Добавить нишу = добавить case +
 * шаблоны в {@see FlowTemplates}.
 */
enum BusinessType: string
{
    case Nails = 'nails';
    case Barbershop = 'barbershop';
    case Beauty = 'beauty';
    case Tattoo = 'tattoo';
    case Sales = 'sales';
    case B2B = 'b2b';

    public function label(): string
    {
        return match ($this) {
            self::Nails => 'Маникюр / ногтевой сервис',
            self::Barbershop => 'Барбершоп',
            self::Beauty => 'Косметология',
            self::Tattoo => 'Тату-студия',
            self::Sales => 'Продажи',
            self::B2B => 'B2B',
        };
    }
}
