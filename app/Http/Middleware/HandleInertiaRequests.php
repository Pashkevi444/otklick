<?php

namespace App\Http\Middleware;

use App\Services\AnnouncementService;
use App\Services\DashboardCardService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'roleLabel' => $user->role->label(),
                    'isOwner' => $user->isOwner(),
                    'allowedSections' => $user->allowedSections(),
                    'permissions' => $user->effectivePermissions(),
                    'tenantId' => $user->tenant_id,
                    'tenant' => $user->tenant_id === null ? null : [
                        'id' => $user->tenant->id,
                        'name' => $user->tenant->name,
                        'plan' => $user->tenant->plan->label(),
                        'planKey' => $user->tenant->plan->value,
                        'features' => $user->tenant->features()->toArray(),
                        'accessExpiresAt' => $user->tenant->access_expires_at?->toDateString(),
                        'hasActiveAccess' => $user->tenant->hasActiveAccess(),
                    ],
                ],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'status' => fn () => $request->session()->get('status'),
                'telegramLink' => fn () => $request->session()->get('telegram_link'),
            ],
            // Супер-админ вошёл в кабинет бизнеса (impersonation) — для баннера выхода.
            'impersonating' => $request->session()->has('impersonator_id'),
            // Ссылка на трекер ошибок (self-hosted GlitchTip/Sentry) — только супер-админу.
            'errorTrackingUrl' => $user !== null && $user->isSuperAdmin()
                ? config('services.error_tracking_url')
                : null,
            // Состояния плашек дашборда (глобально от СУ): новое/обновлено/тех. работы.
            'cardStates' => fn (): array => app(DashboardCardService::class)->statesForFrontend(),
            // Непрочитанные анонсы тенанта (для подсветки пунктов меню «Новости»/«Обновления»).
            'announcementsUnread' => $user?->tenant_id === null
                ? null
                : fn (): array => app(AnnouncementService::class)->unreadCounts(),
        ];
    }
}
