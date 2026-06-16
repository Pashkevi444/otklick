<?php

declare(strict_types=1);

namespace App\Services;

use App\Llm\Contracts\LlmClient;

/**
 * Определяет имя клиента нейросетью. Люди в ответ на вопрос «как вас зовут?»
 * пишут просто «Павел», без конструкций «меня зовут» — поэтому имя не ищется
 * регуляркой по тексту, а отдаётся модели на классификацию.
 *
 * Срабатывает только тогда, когда бот в предыдущей реплике действительно
 * спрашивал имя — иначе короткие ответы вроде «да»/«завтра» не дёргают модель.
 */
final readonly class NameDetector
{
    /** Реплики бота, после которых ждём имя. */
    private const array NAME_PROMPTS = [
        'как вас зовут',
        'как к вам обращаться',
        'как вас называть',
        'ваше имя',
        'назовите имя',
        'назовите ваше имя',
        'подскажите ваше имя',
        'подскажите, как вас',
        'представьтесь',
    ];

    public function __construct(private LlmClient $llm) {}

    /**
     * Если предыдущая реплика бота просила имя — пытается извлечь имя из ответа
     * клиента. Возвращает нормализованное имя или null.
     */
    public function fromReply(?string $previousBotMessage, string $userMessage): ?string
    {
        if (! $this->asksForName($previousBotMessage)) {
            return null;
        }

        return $this->detect($userMessage);
    }

    private function asksForName(?string $botMessage): bool
    {
        if ($botMessage === null || $botMessage === '') {
            return false;
        }

        $haystack = mb_strtolower($botMessage);

        foreach (self::NAME_PROMPTS as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function detect(string $message): ?string
    {
        $text = trim($message);

        // Длинная фраза — это уже не «просто имя», модель не дёргаем.
        if ($text === '' || mb_strlen($text) > 60) {
            return null;
        }

        $system = 'Пользователь отвечает на вопрос «Как вас зовут?». Определи, назвал ли он своё имя. '
            .'Если в сообщении есть имя человека — верни ТОЛЬКО само имя (можно с фамилией), с заглавной буквы, '
            .'без лишних слов, кавычек и знаков препинания. Если имени нет (отказ, вопрос, посторонний текст) — '
            .'верни ровно NONE.';

        $answer = trim($this->llm->generate($system, [
            ['role' => 'user', 'content' => $text],
        ]));

        $answer = trim($answer, " \t\n\r\0\x0B.!?,;:\"'");

        if ($answer === '' || mb_strtoupper($answer) === 'NONE') {
            return null;
        }

        // Имя или имя-фамилия — только буквы (1–2 слова), иначе это не имя.
        if (preg_match('/^[А-Яа-яЁёA-Za-z]{2,30}(?:[ \-][А-Яа-яЁёA-Za-z]{2,30})?$/u', $answer) !== 1) {
            return null;
        }

        return $this->normalize($answer);
    }

    private function normalize(string $name): string
    {
        $words = preg_split('/([ \-])/u', $name, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$name];

        return implode('', array_map(
            fn (string $part): string => preg_match('/^[А-Яа-яЁёA-Za-z]/u', $part) === 1
                ? mb_strtoupper(mb_substr($part, 0, 1)).mb_strtolower(mb_substr($part, 1))
                : $part,
            $words,
        ));
    }
}
