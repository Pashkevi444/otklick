<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Enums\TenantPlan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Текущая подписка тенанта: тариф, срок доступа и доступные возможности.
 * Гейтинг возможностей — TenantPlan::features().
 */
final class SubscriptionController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $tenant = $request->user()?->tenant;

        abort_if($tenant === null, HttpResponse::HTTP_FORBIDDEN);

        $plan = $tenant->plan;

        return Inertia::render('Cabinet/Subscription', [
            'plan' => [
                'key' => $plan->value,
                'label' => $plan->label(),
                'tier' => $plan->tier()->value,
                'isTrial' => $plan === TenantPlan::Trial,
                'isMax' => $plan->tier() === TenantPlan::Max,
                'features' => $plan->features()->toArray(),
                'accessExpiresAt' => $tenant->access_expires_at?->toDateString(),
                'hasActiveAccess' => $tenant->hasActiveAccess(),
            ],
        ]);
    }
}
