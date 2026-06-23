<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasLabel;

/**
 * Сущность, к гриду которой относится сохранённый вид.
 */
enum GridEntity: string implements HasLabel
{
    case Deal = 'deal';
    case Lead = 'lead';
    case Client = 'client';
    case Conversation = 'conversation';

    public function label(): string
    {
        return match ($this) {
            self::Deal => 'Сделки',
            self::Lead => 'Лиды',
            self::Client => 'Клиенты',
            self::Conversation => 'Диалоги',
        };
    }
}
