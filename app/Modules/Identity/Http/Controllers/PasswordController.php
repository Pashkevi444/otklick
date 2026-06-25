<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Requests\UpdatePasswordRequest;
use App\Shared\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Смена собственного пароля (для любого пользователя).
 */
final class PasswordController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Account/Password');
    }

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        // Каст 'hashed' захеширует пароль при сохранении.
        $request->user()->update(['password' => $request->string('password')]);

        return back()->with('success', 'Пароль обновлён.');
    }
}
