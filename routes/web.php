<?php

declare(strict_types=1);

use App\Enums\CrmProvider;
use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\EmailController;
use App\Http\Controllers\Account\PasswordController;
use App\Http\Controllers\Account\TwoFactorController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Cabinet\AnalyticsController;
use App\Http\Controllers\Cabinet\BillingController;
use App\Http\Controllers\Cabinet\BusinessOverviewController;
use App\Http\Controllers\Cabinet\BusinessProfileController;
use App\Http\Controllers\Cabinet\ChannelController;
use App\Http\Controllers\Cabinet\ConversationController;
use App\Http\Controllers\Cabinet\CrmKnowledgeController;
use App\Http\Controllers\Cabinet\DashboardController;
use App\Http\Controllers\Cabinet\IntegrationController;
use App\Http\Controllers\Cabinet\KnowledgeEntryController;
use App\Http\Controllers\Cabinet\NotificationController;
use App\Http\Controllers\Cabinet\SubscriptionController;
use App\Http\Controllers\Cabinet\SuspendedController;
use App\Http\Controllers\Cabinet\WidgetController;
use App\Http\Controllers\Site\HomeController;
use Illuminate\Support\Facades\Route;

/*
 * Разведение по доменам: публичный сайт — на маркетинговом домене, приложение
 * (кабинет + супер-админка + вход) — на бизнес-поддомене. Если домены не заданы
 * (локально) — всё регистрируется на одном хосте, пути не пересекаются.
 */
$onDomain = function (?string $domain, Closure $routes): void {
    $domain ? Route::domain($domain)->group($routes) : $routes();
};

// Публичный сайт (маркетинг).
$onDomain(config('app.marketing_domain'), function (): void {
    Route::get('/', [HomeController::class, 'home'])->name('home');
    Route::get('/contacts', [HomeController::class, 'contacts'])->name('site.contacts');
});

// Приложение: бизнес-поддомен (business.<домен>).
$onDomain(config('app.business_domain'), function (): void {
    // Супер-админка
    Route::middleware(['auth', 'super-admin'])->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
        Route::put('/tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
        Route::put('/tenants/{tenant}/owner-password', [TenantController::class, 'updateOwnerPassword'])->name('tenants.owner-password.update');
        Route::put('/tenants/{tenant}/overrides', [TenantController::class, 'updateOverrides'])->name('tenants.overrides.update');
        Route::delete('/tenants/{tenant}/overrides', [TenantController::class, 'resetOverrides'])->name('tenants.overrides.reset');
        Route::post('/tenants/{tenant}/block', [TenantController::class, 'block'])->name('tenants.block');
        Route::post('/tenants/{tenant}/unblock', [TenantController::class, 'unblock'])->name('tenants.unblock');

        Route::get('/site', [SiteController::class, 'edit'])->name('site.edit');
        Route::put('/site', [SiteController::class, 'update'])->name('site.update');
    });

    // Корень бизнес-домена (business.<домен>/) — карточка бизнеса. На маркетинг-
    // домене «/» занят лендингом, поэтому регистрируем эту «/» только когда домены
    // реально разведены (на проде), чтобы локально не перекрыть лендинг.
    if (config('app.business_domain')) {
        Route::middleware('auth')->get('/', BusinessOverviewController::class)->name('business.home');
    }

    // Кабинет тенанта
    Route::middleware(['auth', 'tenant'])->prefix('cabinet')->name('cabinet.')->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/overview', BusinessOverviewController::class)->name('overview');

        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::post('/analytics/insights/refresh', [AnalyticsController::class, 'refreshInsights'])->name('analytics.insights.refresh');
        Route::get('/analytics/export/{type}', [AnalyticsController::class, 'export'])->name('analytics.export');

        Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');
        Route::post('/channels', [ChannelController::class, 'store'])->name('channels.store');
        Route::delete('/channels/{channel}', [ChannelController::class, 'destroy'])->name('channels.destroy');

        Route::get('/profile', [BusinessProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [BusinessProfileController::class, 'update'])->name('profile.update');

        Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
        Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
        Route::put('/conversations/{conversation}/status', [ConversationController::class, 'setStatus'])->name('conversations.status');

        Route::get('/knowledge', [KnowledgeEntryController::class, 'index'])->name('knowledge.index');
        Route::post('/knowledge', [KnowledgeEntryController::class, 'store'])->name('knowledge.store');
        Route::get('/knowledge/{entry}/edit', [KnowledgeEntryController::class, 'edit'])->name('knowledge.edit');
        Route::put('/knowledge/{entry}', [KnowledgeEntryController::class, 'update'])->name('knowledge.update');
        Route::delete('/knowledge/{entry}', [KnowledgeEntryController::class, 'destroy'])->name('knowledge.destroy');

        // Уведомления владельцу о событиях (email/Telegram).
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/email', [NotificationController::class, 'storeEmail'])->name('notifications.email');
        Route::post('/notifications/telegram', [NotificationController::class, 'connectTelegram'])->name('notifications.telegram');
        Route::put('/notifications/{recipient}/toggle', [NotificationController::class, 'toggle'])->name('notifications.toggle');
        Route::delete('/notifications/{recipient}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

        Route::get('/subscription', SubscriptionController::class)->name('subscription');
        Route::get('/billing', BillingController::class)->name('billing');

        // Веб-виджет (чат на сайт) — доступен на всех тарифах.
        Route::get('/widget', [WidgetController::class, 'index'])->name('widget.index');
        Route::post('/widget', [WidgetController::class, 'store'])->name('widget.store');
        Route::put('/widget/{channel}', [WidgetController::class, 'update'])->name('widget.update');
        Route::delete('/widget/{channel}', [WidgetController::class, 'destroy'])->name('widget.destroy');

        // CRM-интеграции — возможность тарифа «Макс».
        Route::middleware('plan:crm')->group(function (): void {
            Route::get('/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
            Route::post('/integrations/connect/{provider}', [IntegrationController::class, 'store'])
                ->whereIn('provider', array_map(fn (CrmProvider $p): string => $p->value, CrmProvider::cases()))
                ->name('integrations.store');
            Route::post('/integrations/{connection}/verify', [IntegrationController::class, 'verify'])->name('integrations.verify');
            Route::delete('/integrations/{connection}', [IntegrationController::class, 'destroy'])->name('integrations.destroy');

            // База знаний из CRM (нередактируемая, выгружается фоновой задачей).
            Route::get('/knowledge-crm', [CrmKnowledgeController::class, 'index'])->name('knowledge.crm');
            Route::post('/knowledge-crm/sync', [CrmKnowledgeController::class, 'sync'])->name('knowledge.crm.sync');
        });
    });

    // Аккаунт + сервисные страницы (любой авторизованный, без проверки тарифа)
    Route::middleware('auth')->group(function (): void {
        Route::get('/account', [AccountController::class, 'index'])->name('account.settings');

        Route::get('/account/password', [PasswordController::class, 'edit'])->name('account.password.edit');
        Route::put('/account/password', [PasswordController::class, 'update'])->name('account.password.update');

        Route::get('/account/email', [EmailController::class, 'edit'])->name('account.email.edit');
        Route::post('/account/email', [EmailController::class, 'requestChange'])->name('account.email.request');
        Route::post('/account/email/confirm', [EmailController::class, 'confirm'])->name('account.email.confirm');

        Route::get('/account/two-factor', [TwoFactorController::class, 'show'])->name('account.2fa.show');
        Route::post('/account/two-factor', [TwoFactorController::class, 'enable'])->name('account.2fa.enable');
        Route::post('/account/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('account.2fa.confirm');
        Route::delete('/account/two-factor', [TwoFactorController::class, 'disable'])->name('account.2fa.disable');

        Route::get('/suspended', SuspendedController::class)->name('suspended');
    });

    require __DIR__.'/auth.php';
});
