<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Shared\Support\HomeRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Вход/выход (session-аутентификация, паттерн Breeze). Тонкий: рендер формы,
 * делегирование аутентификации в LoginRequest, редирект по роли.
 */
final class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        // Регистрация — только через супер-админа (invite-only). Кто пришёл без
        // аккаунта (в т.ч. из маркетплейса YClients) — ведём на заявку, а не в тупик.
        return Inertia::render('Auth/Login', [
            'requestAccessUrl' => route('site.contacts'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $user = $request->user();

        // Включена 2FA — не завершаем вход, отправляем на второй фактор.
        if ($user->hasTwoFactorEnabled()) {
            $remember = $request->boolean('remember');
            Auth::guard('web')->logout();
            $request->session()->put('login.2fa.id', $user->id);
            $request->session()->put('login.2fa.remember', $remember);

            return redirect()->route('two-factor.login');
        }

        $request->session()->regenerate();

        return redirect()->intended(HomeRedirect::for($user));
    }

    public function destroy(Request $request): HttpResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // На лендинг (маркетинг-домен). Кабинет/выход живут на бизнес-поддомене,
        // а home — на другом домене, поэтому обычный 302 Inertia пытается
        // follow'нуть через fetch и упирается в CORS («Не удалось выполнить
        // запрос»). Inertia::location отдаёт 409 + X-Inertia-Location → клиент
        // делает полноценный переход на лендинг без ошибки.
        return Inertia::location(route('home'));
    }
}
