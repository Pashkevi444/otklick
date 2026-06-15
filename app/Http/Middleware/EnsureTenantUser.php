<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Допускает к кабинету только пользователя, привязанного к тенанту. Супер-админ
 * (tenant_id = null) работает в своей панели и в кабинет не заходит — иначе при
 * отсутствии тенант-контекста он бы видел данные всех тенантов.
 */
final class EnsureTenantUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null || $user->tenant_id === null, Response::HTTP_FORBIDDEN);

        // Доступ к кабинету приостановлен (блокировка или истёкший срок).
        if ($user->tenant !== null && ! $user->tenant->hasActiveAccess()) {
            return redirect()->route('suspended');
        }

        return $next($request);
    }
}
