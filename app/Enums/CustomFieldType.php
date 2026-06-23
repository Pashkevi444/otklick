<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasLabel;

/**
 * Тип кастомного поля бизнеса. Определяет ввод в UI и приведение значения при
 * сохранении в jsonb `custom`.
 */
enum CustomFieldType: string implements HasLabel
{
    case Text = 'text';
    case Number = 'number';
    case Select = 'select';
    case Date = 'date';
    case Bool = 'bool';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Текст',
            self::Number => 'Число',
            self::Select => 'Список',
            self::Date => 'Дата',
            self::Bool => 'Да/Нет',
        };
    }

    /** Поле со списком фиксированных вариантов (нужны `options`). */
    public function hasOptions(): bool
    {
        return $this === self::Select;
    }
}
