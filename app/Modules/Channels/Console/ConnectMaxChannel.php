<?php

declare(strict_types=1);

namespace App\Modules\Channels\Console;

use App\Modules\Channels\Services\ChannelService;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Console\Command;
use Throwable;

/**
 * Ручное подключение бота MAX к тенанту (онбординг/тестирование). В вебе эту
 * операцию заменяет форма в кабинете тенанта.
 */
final class ConnectMaxChannel extends Command
{
    protected $signature = 'channel:connect-max {tenant : UUID тенанта} {token : Токен бота MAX}';

    protected $description = 'Подключает бота MAX к тенанту (работает через long polling, см. max:poll)';

    public function handle(ChannelService $channels, TenantInitializer $tenancy): int
    {
        $tenantId = (string) $this->argument('tenant');

        try {
            $channel = $tenancy->run(
                $tenantId,
                fn () => $channels->connectMax($tenantId, (string) $this->argument('token')),
            );
        } catch (Throwable $e) {
            $this->error("Не удалось подключить: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Канал подключён: {$channel->id}");
        $this->line('Апдейты забирает max:poll.');

        return self::SUCCESS;
    }
}
