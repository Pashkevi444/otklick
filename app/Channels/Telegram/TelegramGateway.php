<?php

declare(strict_types=1);

namespace App\Channels\Telegram;

use App\Channels\Contracts\MessengerGateway;
use App\Models\Channel;
use Illuminate\Support\Facades\Http;

/**
 * Клиент Telegram Bot API. Отправляет ответы и регистрирует вебхук.
 * Токен бота берётся из кред канала (per-tenant).
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
     * Регистрирует URL вебхука у Telegram с secret_token: Telegram будет слать
     * его в заголовке X-Telegram-Bot-Api-Secret-Token (верификация источника).
     */
    public function setWebhook(Channel $channel, string $url, string $secretToken): void
    {
        $this->call($channel->botToken(), 'setWebhook', [
            'url' => $url,
            'secret_token' => $secretToken,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function call(?string $botToken, string $method, array $params): void
    {
        // Таймауты держим короткими: если api.telegram.org недоступен (в РФ он
        // заблокирован по IPv4 — нужен IPv6/прокси), запрос должен быстро упасть
        // с понятной ошибкой, а не висеть 30+ секунд и ловить таймаут шлюза (500).
        $request = Http::asJson()
            ->connectTimeout(5)
            ->timeout(8)
            ->retry(2, 300, throw: false);

        // В РФ IPv4 Telegram заблокирован — без форса Guzzle сначала висит на
        // IPv4-таймауте. Форсируем IPv6, чтобы вызовы были мгновенными.
        if ($this->forceIpv6) {
            $request->withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V6]]);
        }

        $request->post("{$this->apiUrl}/bot{$botToken}/{$method}", $params)->throw();
    }
}
