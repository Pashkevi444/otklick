<?php

declare(strict_types=1);

namespace Tests\Unit\Vision;

use App\Shared\Vision\YandexImageToText;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class YandexImageToTextTest extends TestCase
{
    private function client(): YandexImageToText
    {
        return new YandexImageToText('https://ai.api.cloud.yandex.net/v1/chat/completions', 'test-key', 'folder-1', 'qwen2.5-vl-7b-instruct');
    }

    public function test_describes_image_and_sends_api_key_and_data_url(): void
    {
        Http::fake(['*ai.api.cloud.yandex.net*' => Http::response([
            'choices' => [['message' => ['content' => 'Мужская стрижка андеркат, короткие виски.']]],
        ])]);

        $text = $this->client()->describe('JPEG-BYTES', 'image/jpeg', 'хочу так же');

        $this->assertSame('Мужская стрижка андеркат, короткие виски.', $text);
        Http::assertSent(function ($request): bool {
            $body = $request->data();
            $content = $body['messages'][1]['content'] ?? [];

            return $request->hasHeader('Authorization', 'Api-Key test-key')
                && str_contains((string) $body['model'], 'qwen2.5-vl-7b-instruct')
                && is_array($content)
                && ($content[0]['type'] ?? null) === 'image_url'
                && str_starts_with((string) ($content[0]['image_url']['url'] ?? ''), 'data:image/jpeg;base64,');
        });
    }

    public function test_returns_null_on_empty_content(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '']]]])]);

        $this->assertNull($this->client()->describe('JPEG-BYTES'));
    }

    public function test_returns_null_on_api_error(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->assertNull($this->client()->describe('JPEG-BYTES'));
    }
}
