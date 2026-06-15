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
        ]);
    }

    public function contacts(): Response
    {
        return Inertia::render('Site/Contacts', [
            'site' => $this->present($this->site->current()),
            'loginUrl' => route('login'),
        ]);
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
