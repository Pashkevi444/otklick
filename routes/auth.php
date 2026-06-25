<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\AuthenticatedSessionController;
use App\Modules\Identity\Http\Controllers\PasswordResetController;
use App\Modules\Identity\Http\Controllers\TwoFactorChallengeController;
use Illuminate\Support\Facades\Route;

/*
 * Аутентификация (session, паттерн Breeze). Публичная регистрация не
 * публикуется — тенантов заводит супер-админ.
 */
Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Восстановление пароля по коду из письма (код живёт 6 минут).
    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])
        ->middleware('throttle:6,1')->name('password.email');
    Route::get('reset-password', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])
        ->middleware('throttle:6,1')->name('password.update');

    // Второй фактор при входе (логин ждёт в сессии до ввода кода).
    Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.login');
    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
        ->middleware('throttle:6,1')->name('two-factor.login.store');
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
