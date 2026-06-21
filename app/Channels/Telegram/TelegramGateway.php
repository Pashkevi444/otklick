<?php

declare(strict_types=1);

namespace App\Channels\Telegram;

use App\Channels\Contracts\ChannelGateway;
use App\Channels\Contracts\ReceivesVoice;
use App\DTO\ReplyKeyboard;
use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Клиент Telegram Bot API. Отправляет ответы и забирает апдейты.
 * Токен бота берётся из кред канала (per-tenant).
 *
 * В РФ вебхуки Telegram не работают (входящий путь Telegram→РФ-IPv4 заблокирован,
 * IPv6 для вебхука Telegram не принимает), поэтому апдейты тянем long polling'ом
 * (getUpdates) — это исходящий запрос, который ходит по IPv6.
 */
final readonly class TelegramGateway implements ChannelGateway, ReceivesVoice
{
    public function __construct(
        private string $apiUrl,
        private bool $forceIpv6 = false,
    ) {}

    public function provider(): ChannelType
    {
        return ChannelType::Telegram;
    }

    public function send(Channel $channel, string $chatId, string $text, ?ReplyKeyboard $keyboard = null, array $images = []): void
    {
        if ($text !== '') {
            $this->call($channel->botToken(), 'sendMessage', [
                'chat_id' => $chatId,
                'text' => $text,
                // Reply-кнопки шлют свой текст обычным сообщением; при отсутствии
                // клавиатуры снимаем прошлую, чтобы не висела на не-мастер-ответах.
                'reply_markup' => $this->replyMarkup($keyboard),
            ]);
        }

        // Картинки — настоящими фото (Telegram сам скачает по URL). Сбой sendPhoto
        // НЕ роняет отправку (иначе внешний ретрай зациклит дубли текста) — что не
        // ушло фото, отдаём ссылкой, чтобы клиент всё же увидел.
        $failed = [];
        foreach ($images as $url) {
            try {
                $this->call($channel->botToken(), 'sendPhoto', [
                    'chat_id' => $chatId,
                    'photo' => $url,
                ]);
            } catch (Throwable $e) {
                report($e);
                $failed[] = $url;
            }
        }

        if ($failed !== []) {
            $this->call($channel->botToken(), 'sendMessage', [
                'chat_id' => $chatId,
                'text' => 'Примеры работ: '.implode(' ', $failed),
            ]);
        }
    }

    /**
     * Рендер клавиатуры-подсказки в reply_markup Telegram (JSON-строка):
     * ReplyKeyboardMarkup (кнопки шлют свой текст) либо снятие клавиатуры.
     */
    private function replyMarkup(?ReplyKeyboard $keyboard): string
    {
        if ($keyboard === null || $keyboard->isEmpty()) {
            return (string) json_encode(['remove_keyboard' => true]);
        }

        $rows = array_map(
            fn (array $row): array => array_map(fn (string $label): array => ['text' => $label], $row),
            $keyboard->rows,
        );

        return (string) json_encode([
            'keyboard' => $rows,
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ]);
    }

    /**
     * Регистрирует URL вебхука у Telegram с secret_token. На РФ-хостинге не
     * используется (вебхуки недоступны) — оставлено для не-РФ окружений.
     */
    public function setWebhook(Channel $channel, string $url, string $secretToken): void
    {
        $this->call($channel->botToken(), 'setWebhook', [
            'url' => $url,
            'secret_token' => $secretToken,
        ]);
    }

    /**
     * Снимает вебхук — обязательно перед long polling (иначе getUpdates вернёт
     * 409 Conflict). Заодно валидирует токен (битый → 401).
     */
    public function deleteWebhook(Channel $channel): void
    {
        $this->call($channel->botToken(), 'deleteWebhook', [
            'drop_pending_updates' => false,
        ]);
    }

    /**
     * Забирает апдейты бота через long polling. Возвращает «сырые» апдейты
     * Telegram (как в вебхуке) — их обрабатывает ProcessTelegramUpdate.
     *
     * @return list<array<string, mixed>>
     */
    public function getUpdates(string $botToken, int $offset, int $longPollSeconds = 25): array
    {
        $response = $this->request()
            ->connectTimeout(5)
            ->timeout($longPollSeconds + 10)
            ->get("{$this->apiUrl}/bot{$botToken}/getUpdates", [
                'offset' => $offset,
                'timeout' => $longPollSeconds,
                'allowed_updates' => json_encode(['message']),
            ])
            ->throw();

        /** @var list<array<string, mixed>> $result */
        $result = $response->json('result', []);

        return $result;
    }

    /**
     * Отправляет сообщение и возвращает его telegram message_id (нужен для
     * reply-маппинга в операторском мосте). null — если id не пришёл.
     */
    public function sendReturningId(Channel $channel, string $chatId, string $text): ?int
    {
        $id = $this->request()
            ->connectTimeout(5)
            ->timeout(8)
            ->retry(2, 300, throw: false)
            ->post("{$this->apiUrl}/bot{$channel->botToken()}/sendMessage", ['chat_id' => $chatId, 'text' => $text])
            ->throw()
            ->json('result.message_id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Данные о боте (getMe) — нужен username для диплинка подключения уведомлений.
     *
     * @return array<string, mixed>
     */
    public function getMe(Channel $channel): array
    {
        $response = $this->request()
            ->connectTimeout(5)
            ->timeout(8)
            ->get("{$this->apiUrl}/bot{$channel->botToken()}/getMe")
            ->throw();

        /** @var array<string, mixed> $result */
        $result = $response->json('result', []);

        return $result;
    }

    /**
     * Скачивает голосовое сообщение Telegram (getFile → download). Войс приходит
     * как OGG/Opus — отдаём байты как есть (SpeechKit принимает нативно).
     *
     * @param  array<string, mixed>  $update
     */
    public function downloadVoice(Channel $channel, array $update): ?string
    {
        $message = $update['message'] ?? null;
        $fileId = is_array($message) ? ($message['voice']['file_id'] ?? $message['audio']['file_id'] ?? null) : null;

        if (! is_string($fileId) || $fileId === '') {
            return null;
        }

        $token = $channel->botToken();

        try {
            $filePath = $this->request()
                ->connectTimeout(5)->timeout(8)
                ->get("{$this->apiUrl}/bot{$token}/getFile", ['file_id' => $fileId])
                ->throw()
                ->json('result.file_path');

            if (! is_string($filePath) || $filePath === '') {
                return null;
            }

            $body = $this->request()
                ->connectTimeout(5)->timeout(20)
                ->get("{$this->apiUrl}/file/bot{$token}/{$filePath}")
                ->throw()
                ->body();

            return $body !== '' ? $body : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function call(?string $botToken, string $method, array $params): void
    {
        // Короткие таймауты: недоступность Telegram должна падать быстро с
        // понятной ошибкой, а не висеть и ловить таймаут шлюза.
        $this->request()
            ->connectTimeout(5)
            ->timeout(8)
            ->retry(2, 300, throw: false)
            ->post("{$this->apiUrl}/bot{$botToken}/{$method}", $params)
            ->throw();
    }

    /**
     * Базовый HTTP-клиент. В РФ форсируем IPv6 — иначе Guzzle сперва висит на
     * заблокированном IPv4-таймауте перед переходом на рабочий IPv6.
     */
    private function request(): PendingRequest
    {
        $request = Http::asJson();

        if ($this->forceIpv6) {
            $request->withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V6]]);
        }

        return $request;
    }
}
