<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Обогащает все логи запроса контекстом (пользователь, тенант, маршрут, IP).
 * Laravel автоматически добавляет Context в каждую запись лога — по любой ошибке
 * сразу видно, чей и какой запрос её вызвал. Ничего из логов пользователей не теряется.
 */
final class AddLogContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        Context::add([
            'user_id' => $user?->id,
            'tenant_id' => $user?->tenant_id,
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'route' => $request->route()?->getName(),
        ]);

        return $next($request);
    }
}
