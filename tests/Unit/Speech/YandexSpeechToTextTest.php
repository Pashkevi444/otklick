<?php

declare(strict_types=1);

namespace Tests\Unit\Speech;

use App\Shared\Speech\YandexSpeechToText;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class YandexSpeechToTextTest extends TestCase
{
    private function client(): YandexSpeechToText
    {
        return new YandexSpeechToText('https://stt.api.cloud.yandex.net/speech/v1/stt:recognize', 'test-key', 'folder-1');
    }

    public function test_transcribes_audio_and_sends_api_key_and_oggopus_format(): void
    {
        Http::fake(['*stt.api.cloud.yandex.net*' => Http::response(['result' => 'есть ли запись?'])]);

        $text = $this->client()->transcribe('OGG-BYTES');

        $this->assertSame('есть ли запись?', $text);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Api-Key test-key')
            && str_contains($request->url(), 'format=oggopus')
            && str_contains($request->url(), 'folderId=folder-1'));
    }

    public function test_returns_null_on_empty_result(): void
    {
        Http::fake(['*' => Http::response(['result' => ''])]);

        $this->assertNull($this->client()->transcribe('OGG-BYTES'));
    }

    public function test_returns_null_on_api_error(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->assertNull($this->client()->transcribe('OGG-BYTES'));
    }
}
