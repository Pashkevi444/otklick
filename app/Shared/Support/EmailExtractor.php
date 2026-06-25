<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * Находит и валидирует email в тексте сообщения. Email необязателен, поэтому при
 * отсутствии/некорректности просто возвращаем null (не блокируем сбор контактов).
 */
final class EmailExtractor
{
    public static function fromText(string $text): ?string
    {
        // Вопрос («это ваш email info@…?») — клиент не оставляет свой email.
        if (str_contains($text, '?')) {
            return null;
        }

        if (preg_match('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/u', $text, $m) !== 1) {
            return null;
        }

        $email = mb_strtolower(trim($m[0]));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }
}
