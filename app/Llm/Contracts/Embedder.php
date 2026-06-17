<?php

declare(strict_types=1);

namespace App\Llm\Contracts;

/**
 * Порт модели эмбеддингов (вектор смысла текста) для семантического поиска (RAG).
 * Бизнес-логика зависит от контракта, а не от провайдера (YandexGPT / fake).
 */
interface Embedder
{
    /**
     * Возвращает эмбеддинг текста — вектор фиксированной размерности.
     *
     * @return list<float>
     */
    public function embed(string $text): array;

    /** Размерность вектора (должна совпадать со схемой knowledge_chunks). */
    public function dimension(): int;
}
