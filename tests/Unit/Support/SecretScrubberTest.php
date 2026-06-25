<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Shared\Support\SecretScrubber;
use PHPUnit\Framework\TestCase;

final class SecretScrubberTest extends TestCase
{
    public function test_redacts_telegram_bot_token_keeps_harmless_params(): void
    {
        // ФЕЙКОВЫЙ токен (не реальный) — только чтобы совпасть с паттерном скрабера.
        $msg = 'cURL error 28: Connection timed out for '.
            'https://api.telegram.org/bot1234567890:FAKEtokenForTestsOnly0000000000000/getUpdates?offset=297572702&timeout=25';

        $out = SecretScrubber::scrub($msg);

        $this->assertStringNotContainsString('FAKEtokenForTestsOnly0000000000000', $out);
        $this->assertStringContainsString('bot[REDACTED]', $out);
        // Безобидные параметры long-polling не трогаем.
        $this->assertStringContainsString('timeout=25', $out);
        $this->assertStringContainsString('offset=297572702', $out);
    }

    public function test_redacts_token_params_and_bearer(): void
    {
        $this->assertStringNotContainsString('s3cr3tValue123', SecretScrubber::scrub('?access_token=s3cr3tValue123&v=5.199'));
        $this->assertStringNotContainsString('greenApiTok123', SecretScrubber::scrub('api_token=greenApiTok123'));
        $this->assertStringNotContainsString('abcdef123456ghij', SecretScrubber::scrub('Authorization: Bearer abcdef123456ghij'));
    }

    public function test_keeps_normal_text(): void
    {
        $text = 'Connection timed out after 5003 milliseconds';
        $this->assertSame($text, SecretScrubber::scrub($text));
    }
}
