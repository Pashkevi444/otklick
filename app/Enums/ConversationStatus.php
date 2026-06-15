<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Статус диалога с клиентом.
 */
enum ConversationStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Открыт',
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
