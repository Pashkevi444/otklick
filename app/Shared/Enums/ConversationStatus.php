<?php

declare(strict_types=1);

namespace App\Shared\Enums;

use App\Shared\Enums\Contracts\HasLabel;

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
     * Грубая стадия для фильтра в журнале: активная / завершённая / нужен человек.
     * Бизнес-результат показываем отдельно (ConversationOutcome).
     */
    public function stageLabel(): string
    {
        return match ($this) {
            self::Open => 'Активные',
            self::NeedsHuman => 'Нужен человек',
            self::Closed => 'Завершённые',
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
