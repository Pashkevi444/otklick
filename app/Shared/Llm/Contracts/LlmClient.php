<?php

declare(strict_types=1);

namespace App\Shared\Llm\Contracts;

/**
 * Порт генеративной модели. Бизнес-логика зависит от этого контракта, а не от
 * конкретного провайдера (GigaChat / YandexGPT / локальный fake).
 */
interface LlmClient
{
    /**
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages
     */
    public function generate(string $systemPrompt, array $messages): string;
}
