<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * Извлекает прямые ссылки на изображения из текста ответа бота, чтобы канал мог
 * отправить их НАСТОЯЩИМИ фото (а не ссылкой). Та же логика, что в веб-виджете
 * (`resources/widget/widget.js`): http(s)-URL с расширением png/jpg/jpeg/gif/webp.
 */
final class ImageUrls
{
    private const string PATTERN = '#https?://[^\s<>"\']+\.(?:png|jpe?g|gif|webp)(?:\?[^\s<>"\']*)?#i';

    /**
     * Возвращает [текст без ссылок-картинок, список URL картинок].
     *
     * @return array{0: string, 1: list<string>}
     */
    public static function split(string $text): array
    {
        if (! preg_match_all(self::PATTERN, $text, $matches)) {
            return [$text, []];
        }

        /** @var list<string> $urls */
        $urls = array_values(array_unique($matches[0]));

        $stripped = (string) preg_replace(self::PATTERN, '', $text);
        // Подчищаем пустые скобки/мусор, оставшиеся после вырезанных ссылок, и пробелы.
        $stripped = (string) preg_replace('/\(\s*[;,\s]*\)/u', '', $stripped);
        $stripped = (string) preg_replace('/[ \t]{2,}/', ' ', $stripped);
        $stripped = (string) preg_replace('/\n{3,}/', "\n\n", $stripped);

        return [trim($stripped), $urls];
    }
}
