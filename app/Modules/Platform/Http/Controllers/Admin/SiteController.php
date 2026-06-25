<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Platform\Http\Requests\UpdateSiteSettingsRequest;
use App\Modules\Platform\Models\SiteSetting;
use App\Modules\Platform\Services\SiteSettingsService;
use App\Shared\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Админка публичного сайта (контент лендинга и контакты) — для супер-админа.
 */
final class SiteController extends Controller
{
    public function __construct(
        private readonly SiteSettingsService $site,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('Admin/Site/Edit', [
            'settings' => $this->present($this->site->current()),
        ]);
    }

    public function update(UpdateSiteSettingsRequest $request): RedirectResponse
    {
        $this->site->update($request->validated());

        return redirect()
            ->route('admin.site.edit')
            ->with('success', 'Контент сайта обновлён.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SiteSetting $site): array
    {
        return [
            'hero_title' => $site->hero_title,
            'hero_subtitle' => $site->hero_subtitle,
            'phone' => $site->phone,
            'email' => $site->email,
            'telegram' => $site->telegram,
            'legal_name' => $site->legal_name,
            'inn' => $site->inn,
            'ogrnip' => $site->ogrnip,
            'access_note' => $site->access_note,
        ];
    }
}
