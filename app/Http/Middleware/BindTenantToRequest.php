<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\TenantInitializer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Выставляет тенант-контекст из залогиненного пользователя на время запроса
 * (in-memory + app.current_tenant для RLS) и гарантированно сбрасывает его в
 * terminate() — Octane-safe.
 *
 * Супер-админ (tenant_id = null) контекст не получает: он работает кросс-тенантно.
 */
final class BindTenantToRequest
{
    public function __construct(private readonly TenantInitializer $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->tenant_id !== null) {
            $this->tenancy->initialize($user->tenant_id);
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->tenancy->flush();
    }
}
