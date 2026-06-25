<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * Тип бизнеса — исходный набор ниш. Используется как seed-источник справочника
 * `business_types` (модель {@see \App\Shared\Models\BusinessType}) в миграциях. Живой
 * справочник, на который ссылаются тенант и шаблоны, — это таблица; этот enum
 * остаётся ради исторических миграций и как канонический список значений.
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
