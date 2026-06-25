<?php

declare(strict_types=1);

namespace App\Shared\Vision;

use App\Shared\Llm\YandexGptClient;
use App\Shared\Vision\Contracts\ImageToText;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Распознавание изображений через vision-модель Yandex Cloud AI по
 * OpenAI-совместимому эндпоинту (`/v1/chat/completions`, тот же, что у
 * {@see YandexGptClient}). Картинка передаётся как data-URL (base64) в
 * мультимодальном `content`. Модель должна быть vision-способной (например,
 * Qwen-VL / Gemma-3, размещаемые в РФ) — задаётся как `gpt://<folder>/<model>/latest`.
 *
 * При сбое возвращает null — бот честно передаёт фото администратору, а не молчит.
 */
final readonly class YandexImageToText implements ImageToText
{
    /** Инструкция модели: краткое деловое описание присланного клиентом фото. */
    private const string INSTRUCTION = <<<'PROMPT'
        Ты описываешь фотографию, которую клиент локального бизнеса (барбершоп,
        салон красоты, студия, мастерская и т.п.) прислал в чат. Опиши на русском,
        что изображено, кратко и по делу — 1–3 предложения. Если это пример
        желаемой услуги (стрижка, окрашивание, маникюр, тату, бровь, интерьер,
        деталь) — опиши стиль и ключевые детали, чтобы администратор понял запрос.
        Не выдумывай того, чего не видно. Не здоровайся и не задавай вопросов —
        только описание.
        PROMPT;

    public function __construct(
        private string $apiUrl,
        private string $apiKey,
        private string $folderId,
        private string $model,
    ) {}

    public function describe(string $image, string $mimeType = 'image/jpeg', string $caption = ''): ?string
    {
        $dataUrl = 'data:'.$mimeType.';base64,'.base64_encode($image);

        $content = [['type' => 'image_url', 'image_url' => ['url' => $dataUrl]]];
        if (trim($caption) !== '') {
            $content[] = ['type' => 'text', 'text' => 'Подпись клиента к фото: '.trim($caption)];
        }

        try {
            $response = Http::withHeaders(['Authorization' => "Api-Key {$this->apiKey}"])
                ->asJson()
                ->timeout(60)
                ->post($this->apiUrl, [
                    'model' => "gpt://{$this->folderId}/{$this->model}/latest",
                    'temperature' => 0.3,
                    // Vision-модели Yandex Cloud — reasoning-типа: токены тратятся на
                    // «размышления» (reasoning_content) И на ответ. Низкий лимит → ответ
                    // (content) не успевает сгенерироваться (null). Даём запас.
                    'max_tokens' => 2500,
                    'messages' => [
                        ['role' => 'system', 'content' => self::INSTRUCTION],
                        ['role' => 'user', 'content' => $content],
                    ],
                ])
                ->throw();

            $text = $response->json('choices.0.message.content');

            return is_string($text) && trim($text) !== '' ? trim($text) : null;
        } catch (Throwable $e) {
            report($e);
            Log::warning('vision.describe_failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
