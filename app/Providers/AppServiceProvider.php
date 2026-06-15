<?php

namespace App\Providers;

use App\Channels\Contracts\MessengerGateway;
use App\Channels\Telegram\TelegramGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TelegramGateway::class, fn (): TelegramGateway => new TelegramGateway(
            (string) config('services.telegram.api_url'),
        ));

        // Единственный канал на данный момент — Telegram. Резолвер по ChannelType
        // появится со вторым каналом (WhatsApp).
        $this->app->bind(MessengerGateway::class, TelegramGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
