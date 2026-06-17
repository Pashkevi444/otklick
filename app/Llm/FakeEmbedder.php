<?php

declare(strict_types=1);

namespace App\Llm;

use App\Llm\Contracts\Embedder;

/**
 * Локальный детерминированный эмбеддер для разработки и тестов без внешнего API.
 * Хэширует слова в «мешок слов» фиксированной размерности и нормирует вектор —
 * у текстов с общими словами вектора близки (косинус ≈ релевантность).
 */
final class FakeEmbedder implements Embedder
{
    public function __construct(private readonly int $dimension = 256) {}

    public function embed(string $text): array
    {
        $vector = array_fill(0, $this->dimension, 0.0);

        preg_match_all('/\p{L}{3,}/u', mb_strtolower($text), $matches);
        foreach ($matches[0] as $word) {
            $vector[crc32($word) % $this->dimension] += 1.0;
        }

        $norm = sqrt(array_sum(array_map(static fn (float $v): float => $v * $v, $vector)));
        if ($norm > 0.0) {
            $vector = array_map(static fn (float $v): float => $v / $norm, $vector);
        }

        return $vector;
    }

    public function dimension(): int
    {
        return $this->dimension;
    }
}
