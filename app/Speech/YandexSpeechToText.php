<?php

declare(strict_types=1);

namespace App\Speech;

use App\Speech\Contracts\SpeechToText;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Распознавание речи через Yandex SpeechKit (синхронный shortAudioRecognition,
 * для коротких голосовых < 30 с). Аутентификация по API-ключу сервисного аккаунта
 * (как у YandexGPT). Telegram/VK-войсы приходят в OGG/Opus — SpeechKit принимает
 * его нативно (format=oggopus), без перекодирования.
 *
 * При сбое возвращает null — бот попросит написать текстом, а не молчит.
 */
final readonly class YandexSpeechToText implements SpeechToText
{
    public function __construct(
        private string $apiUrl,
        private string $apiKey,
        private string $folderId,
    ) {}

    public function transcribe(string $audio, string $format = 'oggopus', string $lang = 'ru-RU'): ?string
    {
        try {
            $response = Http::withHeaders(['Authorization' => "Api-Key {$this->apiKey}"])
                ->withBody($audio, 'application/octet-stream')
                ->post($this->apiUrl.'?'.http_build_query([
                    'topic' => 'general',
                    'lang' => $lang,
                    'format' => $format,
                    'folderId' => $this->folderId,
                ]))
                ->throw();

            $text = $response->json('result');

            return is_string($text) && trim($text) !== '' ? trim($text) : null;
        } catch (Throwable $e) {
            report($e);
            Log::warning('speech.transcribe_failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
