<?php

declare(strict_types=1);

namespace Tests\Unit\Llm;

use App\Llm\YandexEmbedder;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class YandexEmbedderTest extends TestCase
{
    private function embedder(): YandexEmbedder
    {
        return new YandexEmbedder('https://emb.example/v1', 'key', 'folder', 'text-search-query', 256);
    }

    public function test_returns_float_vector(): void
    {
        Http::fake(['*' => Http::response(['embedding' => [0.1, 0.2, 0.3]])]);

        $this->assertSame([0.1, 0.2, 0.3], $this->embedder()->embed('привет'));
    }

    public function test_retries_on_rate_limit_then_succeeds(): void
    {
        // 429 (лимит 10 rps) → ретрай → успех. Бэкофф укорачиваем, чтобы тест был быстрым.
        Http::fakeSequence()
            ->push(['error' => 'rate'], 429)
            ->push(['embedding' => [0.5, 0.6]], 200);

        $this->assertSame([0.5, 0.6], $this->embedder()->embed('акция'));
        Http::assertSentCount(2);
    }
}
