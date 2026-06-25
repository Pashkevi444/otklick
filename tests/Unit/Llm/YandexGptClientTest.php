<?php

declare(strict_types=1);

namespace Tests\Unit\Llm;

use App\Modules\Bot\Services\PromptBuilder;
use App\Shared\Llm\YandexGptClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class YandexGptClientTest extends TestCase
{
    private const string URL = 'https://ai.example/v1/chat/completions';

    private function client(): YandexGptClient
    {
        return new YandexGptClient(self::URL, 'test-key', 'folder-1', 'aliceai-llm');
    }

    public function test_sends_openai_compatible_request_and_returns_text(): void
    {
        Http::fake([self::URL => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => ' Работаем с 9 до 21.']]],
        ])]);

        $answer = $this->client()->generate('Системный промпт', [
            ['role' => 'user', 'content' => 'когда работаете?'],
        ]);

        $this->assertSame('Работаем с 9 до 21.', $answer);

        Http::assertSent(function ($request): bool {
            return $request->hasHeader('Authorization', 'Api-Key test-key')
                && $request['model'] === 'gpt://folder-1/aliceai-llm/latest'
                && $request['messages'][0] === ['role' => 'system', 'content' => 'Системный промпт']
                && $request['messages'][1] === ['role' => 'user', 'content' => 'когда работаете?'];
        });
    }

    public function test_escalates_on_api_error(): void
    {
        Http::fake([self::URL => Http::response([], 500)]);

        $this->assertSame(
            PromptBuilder::ESCALATE,
            $this->client()->generate('Системный промпт', [['role' => 'user', 'content' => 'привет']]),
        );
    }

    public function test_escalates_on_empty_answer(): void
    {
        Http::fake([self::URL => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => '']]],
        ])]);

        $this->assertSame(
            PromptBuilder::ESCALATE,
            $this->client()->generate('Системный промпт', [['role' => 'user', 'content' => 'привет']]),
        );
    }
}
