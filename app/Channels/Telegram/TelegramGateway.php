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
    public function __construct(private string $apiUrl) {}

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
        Http::asJson()
            ->post("{$this->apiUrl}/bot{$botToken}/{$method}", $params)
            ->throw();
    }
}
