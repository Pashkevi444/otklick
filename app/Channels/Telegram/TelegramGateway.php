<?php

declare(strict_types=1);

namespace App\Channels\Telegram;

use App\Channels\Contracts\MessengerGateway;
use App\Models\Channel;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Клиент Telegram Bot API. Отправляет ответы и забирает апдейты.
 * Токен бота берётся из кред канала (per-tenant).
 *
 * В РФ вебхуки Telegram не работают (входящий путь Telegram→РФ-IPv4 заблокирован,
 * IPv6 для вебхука Telegram не принимает), поэтому апдейты тянем long polling'ом
 * (getUpdates) — это исходящий запрос, который ходит по IPv6.
 */
final readonly class TelegramGateway implements MessengerGateway
{
    public function __construct(
        private string $apiUrl,
        private bool $forceIpv6 = false,
    ) {}

    public function send(Channel $channel, string $chatId, string $text): void
    {
        $this->call($channel->botToken(), 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
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
