<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ChannelService;
use App\Tenancy\TenantInitializer;
use Illuminate\Console\Command;

/**
 * Ручное подключение Telegram-бота к тенанту (онбординг/тестирование).
 * В вебе эту операцию заменит форма в кабинете тенанта.
 */
final class ConnectTelegramChannel extends Command
{
    protected $signature = 'channel:connect-telegram {tenant : UUID тенанта} {token : Токен Telegram-бота}';

    protected $description = 'Подключает Telegram-бота к тенанту и регистрирует вебхук';

    public function handle(ChannelService $channels, TenantInitializer $tenancy): int
    {
        $tenantId = (string) $this->argument('tenant');
        $token = (string) $this->argument('token');
        $baseUrl = (string) config('services.telegram.webhook_base_url');

        if ($baseUrl === '') {
            $this->error('Не задан TELEGRAM_WEBHOOK_BASE_URL (или APP_URL) — нужен публичный HTTPS-адрес для setWebhook.');

            return self::FAILURE;
        }

        $channel = $tenancy->run(
            $tenantId,
            fn () => $channels->connectTelegram($tenantId, $token, $baseUrl),
        );

        $this->info("Канал подключён: {$channel->id}");
        $this->line("Вебхук: {$baseUrl}/webhooks/telegram/{$tenantId}/{$channel->id}");

        return self::SUCCESS;
    }
}
