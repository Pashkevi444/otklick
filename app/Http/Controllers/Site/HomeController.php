<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\SiteSettingsService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Публичный сайт (маркетинговый лендинг и контакты). Контент берётся из
 * редактируемых супер-админом настроек сайта.
 */
final class HomeController extends Controller
{
    public function __construct(
        private readonly SiteSettingsService $site,
    ) {}

    public function home(): Response
    {
        return Inertia::render('Site/Home', [
            'site' => $this->present($this->site->current()),
            'loginUrl' => route('login'),
        ])->withViewData($this->meta(
            'Отклик — цифровой администратор для бизнеса: ответы в Telegram, ВКонтакте, MAX, WhatsApp и на сайте, запись клиентов',
            'Отклик — AI-администратор для салонов, барбершопов, клиник и сервиса. Мгновенно отвечает клиентам в Telegram, ВКонтакте, MAX, WhatsApp и на сайте по вашей базе знаний и записывает в CRM. Не теряйте заявки 24/7.',
            route('home'),
            'AI-администратор, чат-бот для бизнеса, бот WhatsApp, автоответы Telegram, бот ВКонтакте, бот MAX, виджет на сайт, запись клиентов, бот для записи, распознавание голосовых сообщений, YClients, 152-ФЗ',
        ));
    }

    public function capabilities(): Response
    {
        return Inertia::render('Site/Capabilities', [
            'site' => $this->present($this->site->current()),
            'loginUrl' => route('login'),
        ])->withViewData($this->meta(
            'Возможности «Отклик» — готовые шаблоны под нишу, интеграции, запуск за вечер',
            'Что умеет «Отклик»: готовые сценарии и база знаний под десятки типов бизнеса, подключение Telegram/ВКонтакте/MAX/WhatsApp и YClients, запуск за один вечер.',
            route('site.capabilities'),
            'возможности чат-бота, готовые сценарии, шаблоны базы знаний, интеграция YClients, бот Telegram WhatsApp ВКонтакте MAX, no-code воронки',
        ) + $this->pageLd([['Главная', route('home')], ['Возможности', route('site.capabilities')]]));
    }

    public function pricing(): Response
    {
        return Inertia::render('Site/Pricing', [
            'site' => $this->present($this->site->current()),
            'loginUrl' => route('login'),
        ])->withViewData($this->meta(
            'Тарифы «Отклик» — AI-администратор для бизнеса, пробный период включён',
            'Тарифы «Отклик»: пробный период бесплатно, «Стандарт» и «Макс» с CRM, сценариями и аналитикой, индивидуальный для корпоративных клиентов.',
            route('site.pricing'),
            'тарифы чат-бот, стоимость AI-администратора, бот для бизнеса цена, пробный период',
        ) + $this->pageLd([['Главная', route('home')], ['Тарифы', route('site.pricing')]], [$this->priceOffers()]));
    }

    public function contacts(): Response
    {
        return Inertia::render('Site/Contacts', [
            'site' => $this->present($this->site->current()),
            'loginUrl' => route('login'),
        ])->withViewData($this->meta(
            'Контакты — Отклик, AI-администратор для бизнеса',
            'Связаться с командой «Отклик»: телефон, почта, Telegram. AI-администратор для локального бизнеса — ответы клиентам и запись в CRM.',
            route('site.contacts'),
        ) + $this->pageLd([['Главная', route('home')], ['Контакты', route('site.contacts')]]));
    }

    /**
     * Политика конфиденциальности (152-ФЗ). Публичная страница — ссылку на неё
     * требуют площадки-партнёры (YClients Marketplace) и закон о ПДн.
     */
    public function privacy(): Response
    {
        return Inertia::render('Site/Privacy', [
            'site' => $this->present($this->site->current()),
            'loginUrl' => route('login'),
        ])->withViewData($this->meta(
            'Политика конфиденциальности — Отклик',
            'Политика обработки персональных данных сервиса «Отклик» (152-ФЗ): какие данные собираем, как храним и защищаем.',
            route('site.privacy'),
        ) + $this->pageLd([['Главная', route('home')], ['Конфиденциальность', route('site.privacy')]]));
    }

    /** Публичная оферта (договор оказания услуг). */
    public function offer(): Response
    {
        return Inertia::render('Site/Offer', [
            'site' => $this->present($this->site->current()),
            'loginUrl' => route('login'),
        ])->withViewData($this->meta(
            'Публичная оферта — Отклик',
            'Публичная оферта на оказание услуг сервиса «Отклик»: предмет договора, тарифы, оплата, ответственность сторон, реквизиты.',
            route('site.offer'),
        ) + $this->pageLd([['Главная', route('home')], ['Оферта', route('site.offer')]]));
    }

    /** Пользовательское соглашение (условия использования). */
    public function terms(): Response
    {
        return Inertia::render('Site/Terms', [
            'site' => $this->present($this->site->current()),
            'loginUrl' => route('login'),
        ])->withViewData($this->meta(
            'Пользовательское соглашение — Отклик',
            'Условия использования сервиса «Отклик»: регистрация, правила, ответственность, интеллектуальная собственность.',
            route('site.terms'),
        ) + $this->pageLd([['Главная', route('home')], ['Пользовательское соглашение', route('site.terms')]]));
    }

    /** Согласие на обработку персональных данных. */
    public function consent(): Response
    {
        return Inertia::render('Site/Consent', [
            'site' => $this->present($this->site->current()),
            'loginUrl' => route('login'),
        ])->withViewData($this->meta(
            'Согласие на обработку персональных данных — Отклик',
            'Согласие на обработку персональных данных при использовании сервиса «Отклик» и отправке форм на сайте (152-ФЗ).',
            route('site.consent'),
        ) + $this->pageLd([['Главная', route('home')], ['Согласие на обработку ПДн', route('site.consent')]]));
    }

    /**
     * SEO-мета для серверного рендера в `<head>` (роботы не видят клиентский
     * Inertia-Head без SSR). Передаём в корневой Blade через withViewData.
     *
     * @return array<string, string>
     */
    private function meta(string $title, string $description, string $canonical, ?string $keywords = null): array
    {
        $meta = [
            'metaTitle' => $title,
            'metaDescription' => $description,
            'metaCanonical' => $canonical,
        ];

        if ($keywords !== null) {
            $meta['metaKeywords'] = $keywords;
        }

        return $meta;
    }

    /**
     * Per-page Schema.org-разметка: хлебные крошки (+ опционально доп. сущности,
     * напр. Product/Offer на тарифах). Рендерится в Blade как отдельный JSON-LD.
     *
     * @param  list<array{0: string, 1: string}>  $trail  [[название, url], …]
     * @param  list<array<string, mixed>>  $extra  доп. graph-объекты
     * @return array{pageJsonLd: string}
     */
    private function pageLd(array $trail, array $extra = []): array
    {
        $items = [];
        foreach ($trail as $i => $t) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $t[0], 'item' => $t[1]];
        }

        $graph = [['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items]];
        foreach ($extra as $g) {
            $graph[] = $g;
        }

        $payload = count($graph) === 1 ? $graph[0] : $graph;

        return ['pageJsonLd' => (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
    }

    /**
     * Product + Offers для страницы тарифов (rich-результаты по цене в поиске).
     *
     * @return array<string, mixed>
     */
    private function priceOffers(): array
    {
        $offer = fn (string $name, string $price): array => [
            '@type' => 'Offer',
            'name' => $name,
            'price' => $price,
            'priceCurrency' => 'RUB',
            'availability' => 'https://schema.org/InStock',
            'url' => route('site.pricing'),
        ];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => 'Отклик — AI-администратор для бизнеса',
            'description' => 'AI-администратор: ответы клиентам в Telegram, ВКонтакте, MAX, WhatsApp и на сайте и запись в CRM.',
            'brand' => ['@type' => 'Brand', 'name' => 'Отклик'],
            'offers' => [
                $offer('Пробный', '0'),
                $offer('Стандарт', '3599'),
                $offer('Макс', '5599'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SiteSetting $site): array
    {
        return [
            'heroTitle' => $site->hero_title,
            'heroSubtitle' => $site->hero_subtitle,
            'phone' => $site->phone,
            'email' => $site->email,
            'telegram' => $site->telegram,
            'legalName' => $site->legal_name,
            'inn' => $site->inn,
            'ogrnip' => $site->ogrnip,
            'accessNote' => $site->access_note,
        ];
    }
}
