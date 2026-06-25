<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Identity\Contracts\IdentityApi;
use App\Shared\Http\Controller;
use App\Shared\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Вход супер-админа в кабинет бизнеса (impersonation): «авторизоваться под
 * бизнес». Исходный супер-админ запоминается в сессии, чтобы можно было выйти
 * обратно. Запуск — только из супер-админки; выход — из кабинета бизнеса.
 */
final class ImpersonationController extends Controller
{
    private const string KEY = 'impersonator_id';

    public function __construct(private readonly IdentityApi $users) {}

    public function start(string $tenant): RedirectResponse
    {
        $owner = $this->users->ownerOf($tenant);

        abort_if($owner === null, Response::HTTP_NOT_FOUND, 'У бизнеса нет владельца.');

        // Запоминаем настоящего супер-админа, входим под владельца.
        session()->put(self::KEY, Auth::id());
        Auth::login($owner);

        return redirect('/cabinet');
    }

    public function stop(): RedirectResponse
    {
        $adminId = session()->pull(self::KEY);

        if ($adminId !== null) {
            // Супер-админ без тенант-скоупа — снимаем глобальные scopes для поиска.
            $admin = User::query()->withoutGlobalScopes()->find($adminId);
            if ($admin !== null) {
                Auth::login($admin);
            }
        }

        return redirect()->route('admin.tenants.index');
    }
}
