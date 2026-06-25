<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Shared\Support\ImageUrls;
use PHPUnit\Framework\TestCase;

final class ImageUrlsTest extends TestCase
{
    public function test_no_images_returns_text_unchanged(): void
    {
        [$text, $images] = ImageUrls::split('Мужская стрижка — 1500 ₽.');

        $this->assertSame('Мужская стрижка — 1500 ₽.', $text);
        $this->assertSame([], $images);
    }

    public function test_extracts_image_urls_and_strips_them(): void
    {
        [$text, $images] = ImageUrls::split(
            'Вот примеры: https://otcl1ck.ru/storage/knowledge/a.jpg и https://otcl1ck.ru/storage/knowledge/b.png',
        );

        $this->assertSame([
            'https://otcl1ck.ru/storage/knowledge/a.jpg',
            'https://otcl1ck.ru/storage/knowledge/b.png',
        ], $images);
        $this->assertStringNotContainsString('http', $text);
        $this->assertStringContainsString('Вот примеры', $text);
    }

    public function test_dedups_and_handles_query_strings(): void
    {
        [, $images] = ImageUrls::split('a.webp? нет; https://x.ru/p.webp?v=2 https://x.ru/p.webp?v=2');

        $this->assertSame(['https://x.ru/p.webp?v=2'], $images);
    }

    public function test_ignores_non_image_links(): void
    {
        [$text, $images] = ImageUrls::split('Сайт: https://otcl1ck.ru и прайс https://otcl1ck.ru/price.pdf');

        $this->assertSame([], $images);
        $this->assertStringContainsString('https://otcl1ck.ru', $text);
    }
}
