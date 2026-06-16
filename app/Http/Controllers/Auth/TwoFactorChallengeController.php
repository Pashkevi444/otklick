<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use App\Support\HomeRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Второй фактор при входе: после верного пароля (если у пользователя включена
 * 2FA) логин завершается только после ввода TOTP-кода или резервного кода.
 * Идентификатор пользователя ждёт в сессии (login.2fa.id), сам вход ещё не выполнен.
 */
final class TwoFactorChallengeController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('login.2fa.id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request): HttpResponse|RedirectResponse
    {
        $userId = $request->session()->get('login.2fa.id');

        if ($userId === null) {
            return redirect()->route('login');
        }

        $request->validate(['code' => ['required', 'string']]);

        $user = User::find($userId);

        if ($user === null || ! $this->twoFactor->verify($user, (string) $request->string('code'))) {
            throw ValidationException::withMessages(['code' => 'Неверный код подтверждения.']);
        }

        Auth::guard('web')->login($user, (bool) $request->session()->get('login.2fa.remember', false));
        $request->session()->forget(['login.2fa.id', 'login.2fa.remember']);
        $request->session()->regenerate();

        return redirect()->intended(HomeRedirect::for($user));
    }
}
