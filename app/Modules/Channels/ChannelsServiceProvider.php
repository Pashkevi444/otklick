<?php

declare(strict_types=1);

namespace App\Modules\Channels;

use App\Modules\Channels\Console\ConnectMaxChannel;
use App\Modules\Channels\Console\ConnectTelegramChannel;
use App\Modules\Channels\Console\ConnectVkChannel;
use App\Modules\Channels\Console\PollMaxUpdates;
use App\Modules\Channels\Console\PollTelegramUpdates;
use App\Modules\Channels\Console\PollVkUpdates;
use App\Modules\Channels\Console\PollWhatsAppUpdates;
use App\Modules\Channels\Contracts\MessengerGateway;
use App\Modules\Channels\Max\MaxGateway;
use App\Modules\Channels\Repositories\Contracts\ChannelRepositoryInterface;
use App\Modules\Channels\Repositories\Eloquent\EloquentChannelRepository;
use App\Modules\Channels\Telegram\TelegramGateway;
use App\Modules\Channels\Vk\VkGateway;
use App\Modules\Channels\WhatsApp\WhatsAppGateway;
use Illuminate\Support\ServiceProvider;

/**
 * Модуль «Каналы» (адаптеры мессенджеров + приём входящих): Telegram/VK/MAX/WhatsApp +
 * веб-виджет. Все каналы работают через long polling (вебхуки в РФ недоступны):
 * команды `*:poll` тянут апдейты и ставят джобы Horizon, джобы вызывают общую
 * бизнес-логику (IncomingMessageService). Реестр стратегий каналов (по ChannelType),
 * биндинг репозитория и команды модуля регистрирует сам модуль. Vision/STT — общие
 * AI-порты (App\Vision/App\Speech), сюда приходят через ImageRecognition/VoiceTranscription.
 */
final class ChannelsServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        ChannelRepositoryInterface::class => EloquentChannelRepository::class,
    ];

    public function register(): void
    {
        $this->app->singleton(TelegramGateway::class, fn (): TelegramGateway => new TelegramGateway(
            (string) config('services.telegram.api_url'),
            (bool) config('services.telegram.force_ipv6'),
            config('services.telegram.proxy'),
        ));

        $this->app->singleton(VkGateway::class, fn (): VkGateway => new VkGateway(
            (string) config('services.vk.api_url'),
            (string) config('services.vk.version'),
        ));

        $this->app->singleton(MaxGateway::class, fn (): MaxGateway => new MaxGateway(
            (string) config('services.max.api_url'),
        ));

        $this->app->singleton(WhatsAppGateway::class, fn (): WhatsAppGateway => new WhatsAppGateway(
            (string) config('services.whatsapp.api_url'),
            config('services.whatsapp.proxy'),
        ));

        // Стратегии каналов выбираются по ChannelType. Новый канал = новый
        // ChannelGateway в этом теге.
        $this->app->tag([TelegramGateway::class, VkGateway::class, MaxGateway::class, WhatsAppGateway::class], 'channel.gateways');
        $this->app->singleton(
            ChannelGatewayResolver::class,
            fn ($app): ChannelGatewayResolver => new ChannelGatewayResolver($app->tagged('channel.gateways')),
        );

        // Обратная совместимость: точечный MessengerGateway = Telegram (где ещё
        // не перешли на резолвер).
        $this->app->bind(MessengerGateway::class, TelegramGateway::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PollTelegramUpdates::class,
                PollVkUpdates::class,
                PollMaxUpdates::class,
                PollWhatsAppUpdates::class,
                ConnectTelegramChannel::class,
                ConnectVkChannel::class,
                ConnectMaxChannel::class,
            ]);
        }
    }
}
