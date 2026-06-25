<?php

declare(strict_types=1);

namespace App\Shared\Enums;

use App\Shared\Enums\Contracts\HasLabel;

/**
 * Состояние плашки дашборда, заданное супер-админом глобально (для всех бизнесов).
 * Взаимоисключающие: обычная / новое / обновлено / тех. работы (недоступна).
 */
enum CardState: string implements HasLabel
{
    case None = 'none';
    case New = 'new';
    case Updated = 'updated';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Обычная',
            self::New => 'Новое',
            self::Updated => 'Обновлено',
            self::Maintenance => 'Тех. работы',
        };
    }

    /** Доступна ли плашка к открытию (тех. работы — нет, прямой заход → 403). */
    public function isAvailable(): bool
    {
        return $this !== self::Maintenance;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $s): string => $s->value, self::cases());
    }
}
