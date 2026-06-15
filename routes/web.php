<?php

declare(strict_types=1);

use App\Enums\CrmProvider;
use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Cabinet\BusinessProfileController;
use App\Http\Controllers\Cabinet\ChannelController;
use App\Http\Controllers\Cabinet\DashboardController;
use App\Http\Controllers\Cabinet\IntegrationController;
use App\Http\Controllers\Cabinet\KnowledgeEntryController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('welcome');

// Супер-админка
Route::middleware(['auth', 'super-admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
    Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
});

// Кабинет тенанта
Route::middleware(['auth', 'tenant'])->prefix('cabinet')->name('cabinet.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');
    Route::post('/channels', [ChannelController::class, 'store'])->name('channels.store');
    Route::delete('/channels/{channel}', [ChannelController::class, 'destroy'])->name('channels.destroy');

    Route::get('/profile', [BusinessProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [BusinessProfileController::class, 'update'])->name('profile.update');

    Route::get('/knowledge', [KnowledgeEntryController::class, 'index'])->name('knowledge.index');
    Route::post('/knowledge', [KnowledgeEntryController::class, 'store'])->name('knowledge.store');
    Route::get('/knowledge/{entry}/edit', [KnowledgeEntryController::class, 'edit'])->name('knowledge.edit');
    Route::put('/knowledge/{entry}', [KnowledgeEntryController::class, 'update'])->name('knowledge.update');
    Route::delete('/knowledge/{entry}', [KnowledgeEntryController::class, 'destroy'])->name('knowledge.destroy');

    Route::get('/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
    Route::post('/integrations/connect/{provider}', [IntegrationController::class, 'store'])
        ->whereIn('provider', array_map(fn (CrmProvider $p): string => $p->value, CrmProvider::cases()))
        ->name('integrations.store');
    Route::post('/integrations/{connection}/verify', [IntegrationController::class, 'verify'])->name('integrations.verify');
    Route::delete('/integrations/{connection}', [IntegrationController::class, 'destroy'])->name('integrations.destroy');
});

// Аккаунт (любой авторизованный пользователь)
Route::middleware('auth')->group(function (): void {
    Route::get('/account/password', [PasswordController::class, 'edit'])->name('account.password.edit');
    Route::put('/account/password', [PasswordController::class, 'update'])->name('account.password.update');
});

require __DIR__.'/auth.php';
