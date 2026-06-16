<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\HomeRedirect;
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
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        return redirect()->intended(HomeRedirect::for($request->user()));
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
