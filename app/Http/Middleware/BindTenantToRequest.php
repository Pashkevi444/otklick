<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Tenancy\TenantContext;
use App\Shared\Tenancy\TenantInitializer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        // ВРЕМЕННАЯ ДИАГНОСТИКА (баг impersonation: пустой кабинет) — снять после съёма.
        if ($request->hasSession() && $request->is('cabinet*')) {
            Log::info('tenant.diag', [
                'path' => $request->path(),
                'user' => $user?->email,
                'user_id' => $user?->id,
                'user_tenant_id' => $user?->tenant_id,
                'impersonating' => $request->session()->has('impersonator_id'),
                'context_tenant' => app(TenantContext::class)->id(),
            ]);
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->tenancy->flush();
    }
}
