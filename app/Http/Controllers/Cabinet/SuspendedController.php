<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Services\SiteSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Страница «доступ приостановлен» — показывается тенант-пользователю, когда
 * бизнес заблокирован или истёк срок доступа. С контактами для продления.
 */
final class SuspendedController extends Controller
{
    public function __construct(
        private readonly SiteSettingsService $site,
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $tenant = $request->user()->tenant;

        // Если доступ активен (или это не тенант-пользователь) — здесь делать нечего.
        if ($tenant === null || $tenant->hasActiveAccess()) {
            return redirect()->route('cabinet.dashboard');
        }

        $site = $this->site->current();

        return Inertia::render('Cabinet/Suspended', [
            'reason' => $tenant->is_blocked ? 'blocked' : 'expired',
            'expiredAt' => $tenant->access_expires_at?->toDateString(),
            'contacts' => [
                'phone' => $site->phone,
                'email' => $site->email,
                'telegram' => $site->telegram,
            ],
        ]);
    }
}
