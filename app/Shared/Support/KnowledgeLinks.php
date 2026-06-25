<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * Дописывает ссылки элемента базы знаний в конец текста ответа бота отдельным
 * блоком. Ссылки клиенту полезны (запись, прайс, портфолио), поэтому когда
 * сработал элемент БЗ со ссылкой — она ВСЕГДА попадает в ответ (в RAG-ответе и в
 * сценарном действии «показать элемент базы знаний»). URL берём из данных, минуя
 * LLM (она искажает длинные ссылки).
 */
final class KnowledgeLinks
{
    /**
     * @param  list<array{label?: string, url?: string}>  $links
     */
    public static function append(string $text, array $links): string
    {
        $lines = [];

        foreach ($links as $link) {
            $url = trim((string) ($link['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $label = trim((string) ($link['label'] ?? ''));
            $lines[] = $label !== '' ? "🔗 {$label}: {$url}" : "🔗 {$url}";
        }

        if ($lines === []) {
            return $text;
        }

        $block = implode("\n", array_values(array_unique($lines)));
        $text = trim($text);

        return $text === '' ? $block : $text."\n\n".$block;
    }
}
