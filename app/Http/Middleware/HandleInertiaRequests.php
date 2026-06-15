<?php

namespace App\Http\Middleware;

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
                    'tenantId' => $user->tenant_id,
                    'tenant' => $user->tenant_id === null ? null : [
                        'id' => $user->tenant->id,
                        'name' => $user->tenant->name,
                        'plan' => $user->tenant->plan->label(),
                        'planKey' => $user->tenant->plan->value,
                        'features' => $user->tenant->plan->features()->toArray(),
                        'accessExpiresAt' => $user->tenant->access_expires_at?->toDateString(),
                        'hasActiveAccess' => $user->tenant->hasActiveAccess(),
                    ],
                ],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
