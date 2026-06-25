<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Requests\ResetPasswordWithCodeRequest;
use App\Modules\Identity\Http\Requests\SendPasswordResetCodeRequest;
use App\Modules\Identity\Services\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Восстановление пароля по коду из письма (для владельцев бизнеса).
 * Тонкий: рендер форм + делегирование в PasswordResetService.
 */
final class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordResetService $service) {}

    public function request(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function email(SendPasswordResetCodeRequest $request): RedirectResponse
    {
        $email = (string) $request->string('email');

        $this->service->sendCode($email);

        // Не раскрываем, существует ли email: всегда ведём на ввод кода.
        return redirect()
            ->route('password.reset', ['email' => $email])
            ->with('status', 'Если такой email зарегистрирован, мы отправили код. Он действует '.PasswordResetService::CODE_TTL_MINUTES.' минут.');
    }

    public function reset(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(ResetPasswordWithCodeRequest $request): RedirectResponse
    {
        $ok = $this->service->reset(
            (string) $request->string('email'),
            (string) $request->string('code'),
            (string) $request->string('password'),
        );

        if (! $ok) {
            throw ValidationException::withMessages([
                'code' => 'Неверный или просроченный код.',
            ]);
        }

        return redirect()->route('login')->with('status', 'Пароль изменён. Войдите с новым паролем.');
    }
}
