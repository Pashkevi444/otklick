<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasLabel;

/**
 * Статус диалога с клиентом.
 */
enum ConversationStatus: string implements HasLabel
{
    case Open = 'open';
    case NeedsHuman = 'needs_human';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Открыт',
            self::NeedsHuman => 'Нужен человек',
            self::Closed => 'Закрыт',
        };
    }

    /**
     * Статус нового диалога.
     */
    public static function default(): self
    {
        return self::Open;
    }
}
