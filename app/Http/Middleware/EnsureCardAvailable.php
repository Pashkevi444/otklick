<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\DashboardCard;
use App\Services\DashboardCardService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Блокирует доступ к разделу кабинета, если супер-админ поставил его плашке «тех.
 * работы» (глобально, для всех бизнесов). Раздел определяется по имени маршрута
 * `cabinet.<section>` (= ключ плашки {@see DashboardCard}). Прямой заход на
 * заблокированный раздел → обычный 403 (на дашборде плашка серая и не кликается).
 */
final class EnsureCardAvailable
{
    public function __construct(private readonly DashboardCardService $cards) {}

    public function handle(Request $request, Closure $next): Response
    {
        $name = (string) ($request->route()?->getName() ?? '');
        $section = explode('.', $name)[1] ?? '';

        if (DashboardCard::tryFrom($section) !== null) {
            abort_unless($this->cards->stateFor($section)->isAvailable(), Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
