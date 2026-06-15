<?php

declare(strict_types=1);

namespace App\Llm;

use App\Llm\Contracts\LlmClient;
use App\Services\PromptBuilder;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Адаптер YandexGPT через OpenAI-совместимый эндпоинт Yandex Cloud AI
 * (`/v1/chat/completions`). Аутентификация по API-ключу сервисного аккаунта;
 * модель задаётся как `gpt://<folder_id>/<model>/latest`.
 *
 * При ошибке вызова деградирует к сентинелу эскалации — бот вежливо передаёт
 * вопрос администратору вместо молчания.
 */
final readonly class YandexGptClient implements LlmClient
{
    public function __construct(
        private string $apiUrl,
        private string $apiKey,
        private string $folderId,
        private string $model,
    ) {}

    public function generate(string $systemPrompt, array $messages): string
    {
        try {
            $response = Http::withHeaders(['Authorization' => "Api-Key {$this->apiKey}"])
                ->asJson()
                ->post($this->apiUrl, [
                    'model' => "gpt://{$this->folderId}/{$this->model}/latest",
                    'temperature' => 0.4,
                    'max_tokens' => 1500,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ...$messages,
                    ],
                ])
                ->throw();

            $text = $response->json('choices.0.message.content');

            return is_string($text) && trim($text) !== '' ? trim($text) : PromptBuilder::ESCALATE;
        } catch (Throwable $e) {
            report($e);

            return PromptBuilder::ESCALATE;
        }
    }
}
