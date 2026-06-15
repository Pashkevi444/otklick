<?php

namespace App\Providers;

use App\Channels\Contracts\MessengerGateway;
use App\Channels\Telegram\TelegramGateway;
use App\Crm\CrmGatewayResolver;
use App\Crm\Yclients\YclientsGateway;
use App\Llm\Contracts\LlmClient;
use App\Llm\FakeLlmClient;
use App\Llm\YandexGptClient;
use App\Services\SiteSettingsService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

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

        $this->app->singleton(YclientsGateway::class, fn (): YclientsGateway => new YclientsGateway(
            (string) config('services.yclients.api_url'),
            config('services.yclients.partner_token'),
        ));

        // Реестр CRM-стратегий: новый CRM добавляется в этот тег.
        $this->app->tag([YclientsGateway::class], 'crm.gateways');
        $this->app->singleton(
            CrmGatewayResolver::class,
            fn ($app): CrmGatewayResolver => new CrmGatewayResolver($app->tagged('crm.gateways')),
        );

        // Единственный канал на данный момент — Telegram. Резолвер по ChannelType
        // появится со вторым каналом (WhatsApp).
        $this->app->bind(MessengerGateway::class, TelegramGateway::class);

        $this->app->singleton(LlmClient::class, function (): LlmClient {
            $driver = (string) config('services.llm.driver');

            return match ($driver) {
                'fake' => new FakeLlmClient,
                'yandexgpt' => $this->makeYandexGptClient(),
                default => throw new RuntimeException(
                    "LLM-провайдер «{$driver}» не настроен. Доступны fake и yandexgpt; ".
                    'для gigachat нужен адаптер.'
                ),
            };
        });
    }

    private function makeYandexGptClient(): YandexGptClient
    {
        $apiKey = (string) config('services.llm.yandexgpt.api_key');
        $folderId = (string) config('services.llm.yandexgpt.folder_id');

        if ($apiKey === '' || $folderId === '') {
            throw new RuntimeException(
                'YandexGPT не настроен: задайте YANDEX_API_KEY и YANDEX_FOLDER_ID.'
            );
        }

        return new YandexGptClient(
            apiUrl: (string) config('services.llm.yandexgpt.api_url'),
            apiKey: $apiKey,
            folderId: $folderId,
            model: (string) config('services.llm.yandexgpt.model'),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // SEO: Organization JSON-LD в корневом шаблоне (только полные загрузки страниц).
        View::composer('app', function ($view): void {
            $site = $this->app->make(SiteSettingsService::class)->current();

            $view->with('siteJsonLd', (string) json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => 'Отклик',
                'description' => 'AI-администратор для локального бизнеса: автоответы в Telegram и на сайте, запись клиентов.',
                'url' => config('app.url'),
                'telephone' => $site->phone,
                'email' => $site->email,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        });
    }
}
