<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Shared\Enums\CabinetSection;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ограничивает сотруднику (оператору) доступ к разделам кабинета по его правам
 * (User::permissions). Раздел определяется по имени маршрута `cabinet.<section>`.
 * Владелец и супер-админ — без ограничений.
 */
final class EnsureSectionAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isOwner() && ! $user->isSuperAdmin()) {
            $name = (string) ($request->route()?->getName() ?? '');
            $section = explode('.', $name)[1] ?? '';

            if (in_array($section, CabinetSection::values(), true)) {
                abort_unless($user->allows($section), Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }
}
