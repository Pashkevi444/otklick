<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Shared\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Настройки аккаунта (хаб): смена пароля и смена почты. 2FA — позже.
 */
final class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Account/Settings', [
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
