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

    protected $description = 'Подключает Telegram-бота к тенанту (работает через long polling, см. telegram:poll)';

    public function handle(ChannelService $channels, TenantInitializer $tenancy): int
    {
        $tenantId = (string) $this->argument('tenant');
        $token = (string) $this->argument('token');

        $channel = $tenancy->run(
            $tenantId,
            fn () => $channels->connectTelegram($tenantId, $token),
        );

        $this->info("Канал подключён: {$channel->id}");
        $this->line('Апдейты забирает telegram:poll (вебхук снят).');

        return self::SUCCESS;
    }
}
