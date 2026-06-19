<?php

declare(strict_types=1);

namespace App\Channels\Max;

use App\Channels\Contracts\ChannelGateway;
use App\Channels\Contracts\ReceivesVoice;
use App\DTO\ReplyKeyboard;
use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Клиент Bot API мессенджера MAX (botapi.max.ru). Отправляет ответы
 * (POST /messages) и забирает входящие через long polling (GET /updates с
 * маркером) — как Telegram/VK, сервер сам тянет апдейты, публичный вебхук не нужен.
 *
 * Токен бота передаётся в заголовке `Authorization` (query-параметр в MAX
 * больше не поддерживается). Кред канала — access_token.
 */
final readonly class MaxGateway implements ChannelGateway, ReceivesVoice
{
    public function __construct(
        private string $apiUrl,
    ) {}

    public function provider(): ChannelType
    {
        return ChannelType::Max;
    }

    public function send(Channel $channel, string $chatId, string $text, ?ReplyKeyboard $keyboard = null): void
    {
        // chat_id — в query, тело — NewMessageBody {text}. Короткие таймауты:
        // недоступность MAX должна падать быстро с понятной ошибкой.
        $body = ['text' => $text];

        // MAX-кнопки — inline (callback): нажатие приходит как message_callback с
        // payload = подпись; его обрабатывает ProcessMaxUpdate как обычный ввод.
        $attachment = $this->keyboardAttachment($keyboard);
        if ($attachment !== null) {
            $body['attachments'] = [$attachment];
        }

        $this->request($channel)
            ->connectTimeout(5)
            ->timeout(8)
            ->retry(2, 300, throw: false)
            ->withQueryParameters(['chat_id' => $chatId])
            ->post("{$this->apiUrl}/messages", $body)
            ->throw();
    }

    /**
     * Отвечает на нажатие inline-кнопки (callback) — гасит «часики» у клиента.
     * Сбой не критичен (само сообщение-ответ уже ушло), поэтому без throw.
     */
    public function answerCallback(Channel $channel, string $callbackId): void
    {
        $this->request($channel)
            ->connectTimeout(5)
            ->timeout(8)
            ->retry(1, 300, throw: false)
            ->withQueryParameters(['callback_id' => $callbackId])
            ->post("{$this->apiUrl}/answers", ['notification' => '']);
    }

    /**
     * inline_keyboard-вложение MAX из клавиатуры-подсказки: кнопки типа callback,
     * payload = подпись (её разбирает шаг записи). null — без клавиатуры.
     *
     * @return array{type: string, payload: array{buttons: list<list<array{type: string, text: string, payload: string}>>}}|null
     */
    private function keyboardAttachment(?ReplyKeyboard $keyboard): ?array
    {
        if ($keyboard === null || $keyboard->isEmpty()) {
            return null;
        }

        $buttons = array_map(
            fn (array $row): array => array_map(
                fn (string $label): array => ['type' => 'callback', 'text' => $label, 'payload' => $label],
                $row,
            ),
            $keyboard->rows,
        );

        return ['type' => 'inline_keyboard', 'payload' => ['buttons' => $buttons]];
    }

    /**
     * Данные бота (GET /me) — валидация токена при подключении (битый → 401) и
     * имя/username для отображения. Исключения НЕ глушим: их различает контроллер.
     *
     * @return array<string, mixed>
     */
    public function getMe(Channel $channel): array
    {
        $response = $this->request($channel)
            ->connectTimeout(5)
            ->timeout(8)
            ->get("{$this->apiUrl}/me")
            ->throw();

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        return $data;
    }

    /**
     * Один проход long polling. Возвращает «сырые» апдейты MAX и новый marker для
     * следующего запроса (чтобы не получать события повторно).
     *
     * @return array{updates?: list<array<string, mixed>>, marker?: int}
     */
    public function getUpdates(Channel $channel, ?int $marker, int $longPollSeconds = 30): array
    {
        $params = ['timeout' => $longPollSeconds, 'limit' => 100];

        if ($marker !== null) {
            $params['marker'] = $marker;
        }

        /** @var array{updates?: list<array<string, mixed>>, marker?: int} $data */
        $data = $this->request($channel)
            ->connectTimeout(5)
            ->timeout($longPollSeconds + 10)
            ->get("{$this->apiUrl}/updates", $params)
            ->throw()
            ->json() ?? [];

        return $data;
    }

    /**
     * Извлекает из апдейта MAX конверт входящего сообщения: chat_id (адресат
     * ответа), текст и id (mid). Текст может быть пустым (голосовое/вложение без
     * текста) — решение по нему принимает джоб (расшифровка голоса). null — если
     * это не новое сообщение клиента.
     *
     * @param  array<string, mixed>  $update
     * @return array{chatId: string, text: string, id: string}|null
     */
    public function parseMessage(array $update): ?array
    {
        if (($update['update_type'] ?? null) !== 'message_created') {
            return null;
        }

        $message = $update['message'] ?? null;
        if (! is_array($message)) {
            return null;
        }

        // recipient.chat_id — диалог, куда слать ответ; запасной — id отправителя.
        $chatId = $message['recipient']['chat_id'] ?? $message['sender']['user_id'] ?? null;
        if ($chatId === null) {
            return null;
        }

        $text = is_string($message['body']['text'] ?? null) ? trim($message['body']['text']) : '';

        return [
            'chatId' => (string) $chatId,
            'text' => $text,
            'id' => (string) ($message['body']['mid'] ?? ''),
        ];
    }

    /**
     * Скачивает голосовое из апдейта MAX (вложение audio/voice с url). Формат
     * вложения MAX подтверждаем по первому реальному войсу (лог speech.voice);
     * парсинг гибкий. Возвращает байты аудио или null.
     *
     * @param  array<string, mixed>  $update
     */
    public function downloadVoice(Channel $channel, array $update): ?string
    {
        $attachments = $update['message']['body']['attachments'] ?? null;

        if (! is_array($attachments)) {
            return null;
        }

        foreach ($attachments as $attachment) {
            if (! is_array($attachment) || ! in_array($attachment['type'] ?? null, ['audio', 'voice'], true)) {
                continue;
            }

            $url = $attachment['payload']['url'] ?? $attachment['url'] ?? null;
            if (! is_string($url) || $url === '') {
                continue;
            }

            try {
                $body = Http::connectTimeout(5)->timeout(20)->get($url)->throw()->body();

                return $body !== '' ? $body : null;
            } catch (Throwable $e) {
                report($e);

                return null;
            }
        }

        return null;
    }

    /**
     * Разбирает нажатие inline-кнопки (message_callback): payload кнопки = выбор
     * клиента (дата/время/услуга), его подаём в общий разбор как обычный текст.
     * callbackId нужен, чтобы погасить «часики» (answerCallback).
     *
     * @param  array<string, mixed>  $update
     * @return array{chatId: string, text: string, id: string, callbackId: string}|null
     */
    public function parseCallback(array $update): ?array
    {
        if (($update['update_type'] ?? null) !== 'message_callback') {
            return null;
        }

        $callback = $update['callback'] ?? null;
        $message = $update['message'] ?? null;
        if (! is_array($callback) || ! is_array($message)) {
            return null;
        }

        $chatId = $message['recipient']['chat_id'] ?? $callback['user']['user_id'] ?? null;
        $payload = $callback['payload'] ?? null;
        $callbackId = $callback['callback_id'] ?? null;

        if ($chatId === null || ! is_string($payload) || trim($payload) === '' || ! is_string($callbackId)) {
            return null;
        }

        return [
            'chatId' => (string) $chatId,
            'text' => $payload,
            'id' => (string) ($callback['callback_id'] ?? ''),
            'callbackId' => $callbackId,
        ];
    }

    private function request(Channel $channel): PendingRequest
    {
        return Http::asJson()
            ->withHeaders(['Authorization' => (string) $channel->credential('access_token')]);
    }
}
