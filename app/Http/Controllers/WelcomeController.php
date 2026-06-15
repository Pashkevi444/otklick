<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\HomeRedirect;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class WelcomeController extends Controller
{
    public function __invoke(): Response|RedirectResponse
    {
        // Авторизованного ведём в его раздел.
        if (Auth::check()) {
            return redirect(HomeRedirect::for(Auth::user()));
        }

        return Inertia::render('Welcome', [
            'appName' => config('app.name'),
            'laravelVersion' => Application::VERSION,
            'phpVersion' => PHP_VERSION,
        ]);
    }
}
