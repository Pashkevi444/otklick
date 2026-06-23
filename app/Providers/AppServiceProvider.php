<?php

namespace App\Providers;

use App\Booking\BookingGatewayResolver;
use App\Booking\Yclients\YclientsGateway;
use App\Channels\ChannelGatewayResolver;
use App\Channels\Contracts\MessengerGateway;
use App\Channels\Max\MaxGateway;
use App\Channels\Telegram\TelegramGateway;
use App\Channels\Vk\VkGateway;
use App\Channels\WhatsApp\WhatsAppGateway;
use App\Llm\Contracts\Embedder;
use App\Llm\Contracts\LlmClient;
use App\Llm\FakeEmbedder;
use App\Llm\FakeLlmClient;
use App\Llm\YandexEmbedder;
use App\Llm\YandexGptClient;
use App\Notifications\EmailNotifier;
use App\Notifications\NotifierResolver;
use App\Notifications\TelegramNotifier;
use App\Services\SiteSettingsService;
use App\Speech\Contracts\SpeechToText;
use App\Speech\FakeSpeechToText;
use App\Speech\YandexSpeechToText;
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

        $this->app->singleton(YclientsGateway::class, fn (): YclientsGateway => new YclientsGateway(
            (string) config('services.yclients.api_url'),
            config('services.yclients.partner_token'),
        ));

        // Реестр CRM-стратегий: новый CRM добавляется в этот тег.
        $this->app->tag([YclientsGateway::class], 'booking.gateways');
        $this->app->singleton(
            BookingGatewayResolver::class,
            fn ($app): BookingGatewayResolver => new BookingGatewayResolver($app->tagged('booking.gateways')),
        );

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

        // Реестр нотификаторов: новый канал уведомлений добавляется в этот тег.
        $this->app->tag([EmailNotifier::class, TelegramNotifier::class], 'notifiers');
        $this->app->singleton(
            NotifierResolver::class,
            fn ($app): NotifierResolver => new NotifierResolver($app->tagged('notifiers')),
        );

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

        $this->app->singleton(Embedder::class, function (): Embedder {
            $dimension = (int) config('services.embedder.dimension');
            $driver = (string) config('services.embedder.driver');

            return match ($driver) {
                'fake' => new FakeEmbedder($dimension),
                'yandex' => $this->makeYandexEmbedder($dimension),
                default => throw new RuntimeException("Эмбеддер «{$driver}» не настроен. Доступны fake и yandex."),
            };
        });

        $this->app->singleton(SpeechToText::class, function (): SpeechToText {
            $driver = (string) config('services.speech.driver');

            return match ($driver) {
                'fake' => new FakeSpeechToText,
                'yandex' => $this->makeYandexSpeechToText(),
                default => throw new RuntimeException("Распознавание речи «{$driver}» не настроено. Доступны fake и yandex."),
            };
        });
    }

    private function makeYandexSpeechToText(): YandexSpeechToText
    {
        $apiKey = (string) config('services.speech.yandex.api_key');
        $folderId = (string) config('services.speech.yandex.folder_id');

        if ($apiKey === '' || $folderId === '') {
            throw new RuntimeException('Yandex SpeechKit не настроен: задайте YANDEX_API_KEY и YANDEX_FOLDER_ID.');
        }

        return new YandexSpeechToText(
            apiUrl: (string) config('services.speech.yandex.api_url'),
            apiKey: $apiKey,
            folderId: $folderId,
        );
    }

    private function makeYandexEmbedder(int $dimension): YandexEmbedder
    {
        $apiKey = (string) config('services.embedder.yandex.api_key');
        $folderId = (string) config('services.embedder.yandex.folder_id');

        if ($apiKey === '' || $folderId === '') {
            throw new RuntimeException('Yandex-эмбеддер не настроен: задайте YANDEX_API_KEY и YANDEX_FOLDER_ID.');
        }

        return new YandexEmbedder(
            apiUrl: (string) config('services.embedder.yandex.api_url'),
            apiKey: $apiKey,
            folderId: $folderId,
            model: (string) config('services.embedder.yandex.model'),
            dimension: $dimension,
        );
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
        // SEO: Schema.org-разметка (@graph) в корневом шаблоне — Organization +
        // WebSite + SoftwareApplication, чтобы поисковики понимали бренд и продукт.
        View::composer('app', function ($view): void {
            $site = $this->app->make(SiteSettingsService::class)->current();

            $marketing = config('app.marketing_domain');
            $url = $marketing ? 'https://'.$marketing : rtrim((string) config('app.url'), '/');
            $description = 'AI-администратор для локального бизнеса: мгновенно отвечает клиентам в Telegram, ВКонтакте, MAX, WhatsApp и на сайте по базе знаний и записывает в CRM.';

            $view->with('siteJsonLd', (string) json_encode([
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Organization',
                        '@id' => $url.'/#organization',
                        'name' => 'Отклик',
                        'url' => $url,
                        'logo' => $url.'/logo-otklik-light.svg',
                        'description' => $description,
                        'telephone' => $site->phone,
                        'email' => $site->email,
                    ],
                    [
                        '@type' => 'WebSite',
                        '@id' => $url.'/#website',
                        'name' => 'Отклик',
                        'url' => $url,
                        'inLanguage' => 'ru-RU',
                        'publisher' => ['@id' => $url.'/#organization'],
                    ],
                    [
                        '@type' => 'SoftwareApplication',
                        'name' => 'Отклик',
                        'applicationCategory' => 'BusinessApplication',
                        'operatingSystem' => 'Web',
                        'url' => $url,
                        'description' => $description,
                        'publisher' => ['@id' => $url.'/#organization'],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        });
    }
}
