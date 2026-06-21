<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Вырезает секреты (токены/ключи/пароли) из произвольного текста — чтобы они не
 * утекали в логи и трекер ошибок (CLAUDE.md: «не логировать секреты»). Главный
 * источник утечки — сообщения HTTP-исключений: туда попадает полный URL с токеном
 * (напр. `api.telegram.org/bot<id>:<token>/getUpdates`).
 */
final class SecretScrubber
{
    /** @var array<string, string> */
    private const array PATTERNS = [
        // Токен Telegram-бота: <id>:<auth> (в URL — `bot<id>:<token>`).
        '#bot\d{6,}:[A-Za-z0-9_-]{20,}#' => 'bot[REDACTED]',
        // Токен/ключ в query-строке или паре ключ=значение.
        '#(?<k>access[_-]?token|api[_-]?token|apitoken|token|secret|api[_-]?key|apikey|key|password|pass)(?<sep>=|":\s*"|:\s*)(?<v>[A-Za-z0-9._\-]{8,})#i' => '${k}${sep}[REDACTED]',
        // Authorization: Bearer <token>.
        '#(?<b>Bearer\s+)[A-Za-z0-9._\-]{10,}#i' => '${b}[REDACTED]',
    ];

    public static function scrub(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        return (string) preg_replace(array_keys(self::PATTERNS), array_values(self::PATTERNS), $text);
    }
}
