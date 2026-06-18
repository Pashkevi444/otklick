<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Канало-независимая клавиатура-подсказка под сообщением бота: строки кнопок,
 * подпись кнопки = то, что «отправится» при нажатии. Так нажатие приходит как
 * обычное сообщение (Telegram/VK — reply-кнопки) или как callback с тем же
 * значением (MAX), и попадает в тот же разбор шага записи (`resolveChoice` /
 * `RussianDateParser`) — отдельная инфраструктура колбэков не нужна.
 *
 * Подпись кнопки ОБЯЗАНА быть распознаваемой шагом: для даты содержит «dd.mm»,
 * для времени — «HH:MM», для услуги/мастера — название пункта.
 */
final readonly class ReplyKeyboard
{
    /**
     * @param  list<list<string>>  $rows  строки кнопок (каждая строка — список подписей)
     */
    public function __construct(public array $rows) {}

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    /**
     * Раскладывает плоский список подписей по $perRow кнопок в строке.
     *
     * @param  list<string>  $labels
     */
    public static function grid(array $labels, int $perRow = 3): self
    {
        if ($labels === []) {
            return new self([]);
        }

        /** @var list<list<string>> $rows */
        $rows = array_chunk($labels, max(1, $perRow));

        return new self($rows);
    }

    /**
     * Все подписи одним списком (для рендеринга/тестов).
     *
     * @return list<string>
     */
    public function labels(): array
    {
        return $this->rows === [] ? [] : array_merge(...$this->rows);
    }
}
