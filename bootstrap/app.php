<?php

use App\Http\Middleware\BindTenantToRequest;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\HomeRedirect;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Вебхуки каналов — stateless, вне web-группы (без сессий и CSRF).
            Route::group([], base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // За обратным прокси (Caddy терминирует TLS, проксирует на app по http):
        // доверяем X-Forwarded-*, чтобы Laravel знал про https и не плодил
        // mixed-content на ассетах. App доступен только через Caddy во внутренней сети.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);

        // Уже авторизованный на guest-маршруте (напр. /login при активной
        // remember-сессии) уходит в свою панель по роли, а не на лендинг.
        $middleware->redirectUsersTo(fn (Request $request): string => HomeRedirect::for($request->user()));

        $middleware->web(append: [
            HandleInertiaRequests::class,
            BindTenantToRequest::class,
        ]);

        $middleware->alias([
            'super-admin' => EnsureSuperAdmin::class,
            'tenant' => EnsureTenantUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
