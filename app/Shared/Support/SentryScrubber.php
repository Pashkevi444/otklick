<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Sentry\Event;
use Sentry\EventHint;

/**
 * `before_send`-хук Sentry: чистит секреты из событий перед отправкой в трекер.
 * Подключается в `config/sentry.php` как callable-массив `[self::class, 'scrub']`
 * (не замыкание — чтобы не ломать `config:cache`). Глобальная подстраховка: даже
 * если токен попал в сообщение исключения (URL HTTP-ошибки) — он не уйдёт в GlitchTip.
 */
final class SentryScrubber
{
    public static function scrub(Event $event, ?EventHint $hint): Event
    {
        $message = $event->getMessage();
        if ($message !== null) {
            $event->setMessage(SecretScrubber::scrub($message));
        }

        foreach ($event->getExceptions() as $exception) {
            $exception->setValue(SecretScrubber::scrub($exception->getValue()));
        }

        return $event;
    }
}
