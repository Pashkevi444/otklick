<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasLabel;

/**
 * Тип анонса площадки: новость или обновление (патч). Механизм общий, цель разная
 * — отдельные ленты в кабинете и в админке.
 */
enum AnnouncementType: string implements HasLabel
{
    case News = 'news';
    case Update = 'update';

    public function label(): string
    {
        return match ($this) {
            self::News => 'Новости',
            self::Update => 'Обновления',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $t): string => $t->value, self::cases());
    }
}
