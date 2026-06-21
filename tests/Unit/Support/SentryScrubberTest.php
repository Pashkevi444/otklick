<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SentryScrubber;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sentry\Event;
use Sentry\ExceptionDataBag;

final class SentryScrubberTest extends TestCase
{
    public function test_scrubs_secrets_from_message_and_exception(): void
    {
        // ФЕЙКОВЫЙ токен (не реальный) — только чтобы совпасть с паттерном скрабера.
        $token = 'bot1234567890:FAKEtokenForTestsOnly0000000000000';

        $event = Event::createEvent();
        $event->setMessage('leaked '.$token);
        $event->setExceptions([
            new ExceptionDataBag(new RuntimeException('cURL for https://api.telegram.org/'.$token.'/getUpdates')),
        ]);

        $out = SentryScrubber::scrub($event, null);

        $this->assertNotNull($out);
        $this->assertStringNotContainsString('FAKEtokenForTestsOnly0000000000000', (string) $out->getMessage());
        $this->assertStringNotContainsString('FAKEtokenForTestsOnly0000000000000', $out->getExceptions()[0]->getValue());
        $this->assertStringContainsString('bot[REDACTED]', $out->getExceptions()[0]->getValue());
    }
}
