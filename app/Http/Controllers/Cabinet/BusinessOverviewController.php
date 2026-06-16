<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\DTO\BusinessProfile;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Карточка бизнеса — витрина с основной информацией (аватар, описание,
 * контакты, тариф). Это «домашняя» страница кабинета: открывается по клику на
 * логотип и на корне бизнес-домена.
 *
 * Используется и как страница кабинета (под auth+tenant), и как корень
 * бизнес-домена (под auth) — поэтому роли/доступ разруливаем здесь.
 */
final class BusinessOverviewController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.tenants.index');
        }

        $tenant = $user->tenant;

        if ($tenant === null) {
            return redirect()->route('login');
        }

        if (! $tenant->hasActiveAccess()) {
            return redirect()->route('suspended');
        }

        $profile = BusinessProfile::fromArray($tenant->settings['profile'] ?? []);

        return Inertia::render('Cabinet/Overview', [
            'business' => [
                'name' => $tenant->name,
                'plan' => $tenant->plan->value,
                'planLabel' => $tenant->plan->label(),
                ...$profile->toArray(),
            ],
        ]);
    }
}
