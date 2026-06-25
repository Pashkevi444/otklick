<?php

declare(strict_types=1);

namespace App\Shared\Llm;

use App\Modules\Bot\Services\PromptBuilder;
use App\Shared\Llm\Contracts\LlmClient;

/**
 * Локальная детерминированная «модель» для разработки и тестов без внешнего API.
 *
 * Ищет среди строк базы знаний в системном промпте (строки, начинающиеся с «• »)
 * самую релевантную последнему сообщению пользователя по пересечению слов и
 * возвращает её. Если совпадений нет — возвращает уточняющий вопрос (сентинел
 * CLARIFY), как это сделал бы реальный провайдер по инструкции в промпте.
 */
final class FakeLlmClient implements LlmClient
{
    private const string KNOWLEDGE_PREFIX = '• ';

    public function generate(string $systemPrompt, array $messages): string
    {
        $question = $this->lastUserMessage($messages);

        if ($question === null) {
            return PromptBuilder::ESCALATE;
        }

        $questionWords = $this->words($question);
        $best = null;
        $bestScore = 0;

        foreach (preg_split('/\R/', $systemPrompt) ?: [] as $line) {
            $line = trim($line);

            if (! str_starts_with($line, self::KNOWLEDGE_PREFIX)) {
                continue;
            }

            $knowledge = trim(substr($line, strlen(self::KNOWLEDGE_PREFIX)));
            $score = count(array_intersect($questionWords, $this->words($knowledge)));

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $knowledge;
            }
        }

        if ($bestScore > 0 && $best !== null) {
            return $best;
        }

        return PromptBuilder::CLARIFY.' Подскажите, пожалуйста, что именно вас интересует?';
    }

    /**
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages
     */
    private function lastUserMessage(array $messages): ?string
    {
        foreach (array_reverse($messages) as $message) {
            if ($message['role'] === 'user') {
                return $message['content'];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function words(string $text): array
    {
        preg_match_all('/\p{L}{4,}/u', mb_strtolower($text), $matches);

        return array_values(array_unique($matches[0]));
    }
}
