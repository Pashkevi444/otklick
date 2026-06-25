<?php

declare(strict_types=1);

namespace App\Shared\Llm;

/**
 * Сентинелы-маркеры протокола LLM↔бот (`[[ESCALATE]]` и т.п.): модель возвращает их
 * в тексте ответа, пайплайн бота на них реагирует (эскалация/запись/уточнение).
 * Общий словарь между LLM-портом (`App\Shared\Llm`) и модулем `Bot` — поэтому в
 * общем ядре, а не в Bot (иначе Shared зависел бы от модуля). `Bot\PromptBuilder`
 * переэкспортит их как свои константы для удобства, но источник истины — здесь.
 */
final class LlmMarkers
{
    public const string ESCALATE = '[[ESCALATE]]';

    public const string CLARIFY = '[[CLARIFY]]';

    public const string BOOKED = '[[BOOKED]]';

    public const string CANCELLED = '[[CANCELLED]]';

    public const string BOOK = '[[BOOK]]';

    public const string PHOTOS = '[[PHOTOS]]';
}
