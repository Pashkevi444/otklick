<?php

declare(strict_types=1);

namespace App\Llm;

use App\Llm\Contracts\Embedder;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Эмбеддинги Yandex Cloud Foundation Models (textEmbedding). Тот же API-ключ и
 * folder, что у YandexGPT. Один и тот же модельный URI используется для
 * индексации и для запросов — чтобы вектора были в одном пространстве.
 *
 * При сбое бросает исключение — вызывающий слой (ретривер/индексатор) мягко
 * деградирует (бот переходит на «вся база в промпт»).
 */
final readonly class YandexEmbedder implements Embedder
{
    public function __construct(
        private string $apiUrl,
        private string $apiKey,
        private string $folderId,
        private string $model,
        private int $dimension,
    ) {}

    public function embed(string $text): array
    {
        $embedding = Http::withHeaders(['Authorization' => "Api-Key {$this->apiKey}"])
            ->asJson()
            ->post($this->apiUrl, [
                'modelUri' => "emb://{$this->folderId}/{$this->model}/latest",
                'text' => $text,
            ])
            ->throw()
            ->json('embedding');

        if (! is_array($embedding) || $embedding === []) {
            throw new RuntimeException('Yandex embedding: пустой ответ.');
        }

        return array_map(static fn ($v): float => (float) $v, array_values($embedding));
    }

    public function dimension(): int
    {
        return $this->dimension;
    }
}
