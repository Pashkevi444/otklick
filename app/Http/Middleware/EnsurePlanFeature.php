<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Допускает к маршруту только если тариф тенанта включает заданную возможность.
 * Использование: ->middleware('plan:crm'). Источник матрицы — TenantPlan::features().
 */
final class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = $request->user()?->tenant;

        abort_if($tenant === null, Response::HTTP_FORBIDDEN);
        abort_unless($tenant->plan->features()->has($feature), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
