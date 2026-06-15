<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Допускает к маршруту только супер-админа (оператора SaaS).
 */
final class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null || ! $user->isSuperAdmin(), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
