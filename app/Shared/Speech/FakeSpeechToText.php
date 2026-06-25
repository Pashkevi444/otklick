<?php

declare(strict_types=1);

namespace App\Shared\Speech;

use App\Shared\Speech\Contracts\SpeechToText;

/**
 * Локальный fake распознавания речи (по умолчанию, без ключей). Возвращает
 * заранее заданный текст — для тестов и локальной разработки.
 */
final class FakeSpeechToText implements SpeechToText
{
    public function __construct(private ?string $transcript = null) {}

    public function transcribe(string $audio, string $format = 'oggopus', string $lang = 'ru-RU'): ?string
    {
        return $this->transcript;
    }
}
