<?php

declare(strict_types=1);

namespace App\Modules\Channels\Console;

use App\Modules\Channels\Services\ChannelService;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Console\Command;
use Throwable;

/**
 * Ручное подключение сообщества ВКонтакте к тенанту (онбординг/тестирование).
 * В вебе эту операцию заменяет форма в кабинете тенанта.
 */
final class ConnectVkChannel extends Command
{
    protected $signature = 'channel:connect-vk {tenant : UUID тенанта} {token : Токен сообщества} {group : id сообщества}';

    protected $description = 'Подключает сообщество ВКонтакте к тенанту (работает через Bots Long Poll, см. vk:poll)';

    public function handle(ChannelService $channels, TenantInitializer $tenancy): int
    {
        $tenantId = (string) $this->argument('tenant');

        try {
            $channel = $tenancy->run(
                $tenantId,
                fn () => $channels->connectVk($tenantId, (string) $this->argument('token'), (string) $this->argument('group')),
            );
        } catch (Throwable $e) {
            $this->error("Не удалось подключить: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Канал подключён: {$channel->id}");
        $this->line('Апдейты забирает vk:poll.');

        return self::SUCCESS;
    }
}
