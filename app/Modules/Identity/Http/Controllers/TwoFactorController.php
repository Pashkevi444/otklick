<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Services\TwoFactorService;
use App\Shared\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Управление двухфакторной аутентификацией (TOTP) в настройках аккаунта.
 */
final class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function show(Request $request): Response
    {
        $user = $request->user();
        $hasSecret = $user->two_factor_secret !== null;
        $enabled = $user->hasTwoFactorEnabled();
        $pending = $hasSecret && ! $enabled;

        return Inertia::render('Account/TwoFactor', [
            'enabled' => $enabled,
            'pending' => $pending,
            'qr' => $pending ? $this->twoFactor->qrCodeInline($user) : null,
            'secret' => $pending ? $user->two_factor_secret : null,
            'recoveryCodes' => $hasSecret ? $this->twoFactor->recoveryCodes($user) : [],
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);

        $this->twoFactor->generate($request->user());

        return back()->with('success', 'Отсканируйте QR в приложении и подтвердите кодом.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);

        if (! $this->twoFactor->confirm($request->user(), (string) $request->string('code'))) {
            return back()->withErrors(['code' => 'Неверный код. Проверьте время на устройстве и попробуйте снова.']);
        }

        return back()->with('success', 'Двухфакторная аутентификация включена.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);

        $this->twoFactor->disable($request->user());

        return back()->with('success', 'Двухфакторная аутентификация отключена.');
    }
}
