<?php

declare(strict_types=1);

namespace App\Channels\Max;

use App\Channels\Contracts\ChannelGateway;
use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Клиент Bot API мессенджера MAX (botapi.max.ru). Отправляет ответы
 * (POST /messages) и забирает входящие через long polling (GET /updates с
 * маркером) — как Telegram/VK, сервер сам тянет апдейты, публичный вебхук не нужен.
 *
 * Токен бота передаётся в заголовке `Authorization` (query-параметр в MAX
 * больше не поддерживается). Кред канала — access_token.
 */
final readonly class MaxGateway implements ChannelGateway
{
    public function __construct(
        private string $apiUrl,
    ) {}

    public function provider(): ChannelType
    {
        return ChannelType::Max;
    }

    public function send(Channel $channel, string $chatId, string $text): void
    {
        // chat_id — в query, тело — NewMessageBody {text}. Короткие таймауты:
        // недоступность MAX должна падать быстро с понятной ошибкой.
        $this->request($channel)
            ->connectTimeout(5)
            ->timeout(8)
            ->retry(2, 300, throw: false)
            ->withQueryParameters(['chat_id' => $chatId])
            ->post("{$this->apiUrl}/messages", ['text' => $text])
            ->throw();
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
     * Извлекает из апдейта MAX входящее текстовое сообщение: chat_id (адресат
     * ответа), текст и id сообщения (mid). null — если это не новое текстовое
     * сообщение клиента.
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
        $text = $message['body']['text'] ?? null;

        if ($chatId === null || ! is_string($text) || trim($text) === '') {
            return null;
        }

        return [
            'chatId' => (string) $chatId,
            'text' => $text,
            'id' => (string) ($message['body']['mid'] ?? ''),
        ];
    }

    private function request(Channel $channel): PendingRequest
    {
        return Http::asJson()
            ->withHeaders(['Authorization' => (string) $channel->credential('access_token')]);
    }
}
