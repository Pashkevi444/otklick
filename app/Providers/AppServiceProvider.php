<?php

namespace App\Providers;

use App\Modules\Platform\Services\SiteSettingsService;
use App\Shared\Llm\Contracts\Embedder;
use App\Shared\Llm\Contracts\LlmClient;
use App\Shared\Llm\FakeEmbedder;
use App\Shared\Llm\FakeLlmClient;
use App\Shared\Llm\YandexEmbedder;
use App\Shared\Llm\YandexGptClient;
use App\Shared\Speech\Contracts\SpeechToText;
use App\Shared\Speech\FakeSpeechToText;
use App\Shared\Speech\YandexSpeechToText;
use App\Shared\Vision\Contracts\ImageToText;
use App\Shared\Vision\FakeImageToText;
use App\Shared\Vision\YandexImageToText;
use Illuminate\Database\Eloquent\Factories\Factory;
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

        $this->app->singleton(ImageToText::class, function (): ImageToText {
            $driver = (string) config('services.vision.driver');

            return match ($driver) {
                'fake' => new FakeImageToText,
                'yandex' => $this->makeYandexImageToText(),
                default => throw new RuntimeException("Распознавание изображений «{$driver}» не настроено. Доступны fake и yandex."),
            };
        });
    }

    private function makeYandexImageToText(): YandexImageToText
    {
        $apiKey = (string) config('services.vision.yandex.api_key');
        $folderId = (string) config('services.vision.yandex.folder_id');

        if ($apiKey === '' || $folderId === '') {
            throw new RuntimeException('Yandex vision не настроен: задайте YANDEX_API_KEY и YANDEX_FOLDER_ID.');
        }

        return new YandexImageToText(
            apiUrl: (string) config('services.vision.yandex.api_url'),
            apiKey: $apiKey,
            folderId: $folderId,
            model: (string) config('services.vision.yandex.model'),
        );
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
        // Модели живут в модулях (App\Modules\X\Models\*), а не только в App\Models —
        // дефолтный резолвер фабрик Laravel их не находит. Маппим модель→фабрику по
        // имени класса: любой неймспейс → Database\Factories\<Имя>Factory (фабрики
        // лежат плоско). Так Model::factory() работает независимо от модуля.
        Factory::guessFactoryNamesUsing(
            static fn (string $modelName): string => 'Database\\Factories\\'.class_basename($modelName).'Factory',
        );

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
