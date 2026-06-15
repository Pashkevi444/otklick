<?php

declare(strict_types=1);

namespace App\Services;

use App\Channels\Telegram\TelegramGateway;
use App\DTO\NewChannelData;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Бизнес-логика подключения каналов к тенанту. Работает с БД через репозиторий,
 * с Telegram — через gateway.
 */
final readonly class ChannelService
{
    public function __construct(
        private ChannelRepositoryInterface $channels,
        private TelegramGateway $telegram,
    ) {}

    /**
     * Подключает Telegram-бота к тенанту: создаёт канал с зашифрованными кредами
     * и регистрирует вебхук с уникальным secret_token.
     */
    public function connectTelegram(string $tenantId, string $botToken, string $webhookBaseUrl): Channel
    {
        $secretToken = Str::random(40);

        // Транзакция: если setWebhook у Telegram упадёт, канал не останется
        // полу-подключённым.
        return DB::transaction(function () use ($tenantId, $botToken, $webhookBaseUrl, $secretToken): Channel {
            $channel = $this->channels->create(new NewChannelData(
                tenantId: $tenantId,
                type: ChannelType::Telegram,
                externalId: $this->botId($botToken),
                botToken: $botToken,
                secretToken: $secretToken,
            ));

            $this->telegram->setWebhook($channel, $this->webhookUrl($webhookBaseUrl, $channel), $secretToken);

            return $channel;
        });
    }

    private function webhookUrl(string $baseUrl, Channel $channel): string
    {
        return rtrim($baseUrl, '/')."/webhooks/telegram/{$channel->tenant_id}/{$channel->id}";
    }

    /**
     * Числовой id бота — префикс токена до двоеточия (бот <id>:<secret>).
     */
    private function botId(string $botToken): ?string
    {
        $id = Str::before($botToken, ':');

        return $id !== '' ? $id : null;
    }
}
