<?php

declare(strict_types=1);

namespace App\Shared\Enums;

use App\Shared\Enums\Contracts\HasLabel;

/**
 * Тип анонса площадки. Сейчас одна лента — «Новости» (отдельная лента
 * «Обновлений» убрана); enum оставлен для типизации и возможного расширения.
 */
enum AnnouncementType: string implements HasLabel
{
    case News = 'news';

    public function label(): string
    {
        return match ($this) {
            self::News => 'Новости',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $t): string => $t->value, self::cases());
    }
}
