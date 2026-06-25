<?php

declare(strict_types=1);

namespace App\Shared\Enums;

use App\Shared\Enums\Contracts\HasLabel;

/**
 * Состояние «пробела бота» (вопроса без ответа): новый, закрыт добавлением в базу
 * знаний, или скрыт бизнесом как нерелевантный.
 */
enum KnowledgeGapStatus: string implements HasLabel
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Новый',
            self::Resolved => 'В базе знаний',
            self::Dismissed => 'Скрыт',
        };
    }
}
