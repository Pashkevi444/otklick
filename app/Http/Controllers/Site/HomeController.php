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
        ));
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
        ));
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
        ));
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
        ));
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
