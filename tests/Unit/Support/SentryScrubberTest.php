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
        $token = 'bot8696382953:AAEZsM_4o9W3vnAqwbD-UvlDTCkIvu6pRqE';

        $event = Event::createEvent();
        $event->setMessage('leaked '.$token);
        $event->setExceptions([
            new ExceptionDataBag(new RuntimeException('cURL for https://api.telegram.org/'.$token.'/getUpdates')),
        ]);

        $out = SentryScrubber::scrub($event, null);

        $this->assertNotNull($out);
        $this->assertStringNotContainsString('AAEZsM_4o9W3vnAqwbD-UvlDTCkIvu6pRqE', (string) $out->getMessage());
        $this->assertStringNotContainsString('AAEZsM_4o9W3vnAqwbD-UvlDTCkIvu6pRqE', $out->getExceptions()[0]->getValue());
        $this->assertStringContainsString('bot[REDACTED]', $out->getExceptions()[0]->getValue());
    }
}
