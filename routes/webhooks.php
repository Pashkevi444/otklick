<?php

declare(strict_types=1);

use App\Http\Controllers\Webhooks\TelegramWebhookController;
use App\Http\Controllers\Widget\WidgetChatController;
use App\Http\Controllers\Yclients\MarketplaceController;
use Illuminate\Support\Facades\Route;

/*
 * Входящие вебхуки каналов. Stateless (без сессий и CSRF) — внешние сервисы их
 * не присылают. Источник верифицируется в контроллере (secret-токен).
 */
Route::post('/webhooks/telegram/{tenant}/{channel}', TelegramWebhookController::class)
    ->name('webhooks.telegram');

/*
 * Публичный API веб-виджета (чат на сайте бизнеса). Stateless + CORS.
 * Доступ к чату изолирован подписанным токеном сессии и origin allow-list.
 */
Route::get('/widget/v1/widget.js', [WidgetChatController::class, 'script'])->name('widget.script');
Route::options('/widget/v1/{tenant}/{channel}/session', [WidgetChatController::class, 'preflight']);
Route::options('/widget/v1/{tenant}/{channel}/message', [WidgetChatController::class, 'preflight']);
Route::post('/widget/v1/{tenant}/{channel}/session', [WidgetChatController::class, 'session'])
    ->middleware('throttle:20,1')->name('widget.session');
Route::post('/widget/v1/{tenant}/{channel}/message', [WidgetChatController::class, 'message'])
    ->middleware('throttle:40,1')->name('widget.message');

/*
 * YClients Marketplace: server-to-server вебхуки. Stateless (без сессий/CSRF).
 * Подлинность — по партнёрскому токену в теле. Registration Redirect (привязка
 * филиала к тенанту) — авторизованный роут /yclients/connect в routes/web.php.
 */
Route::post('/yclients/webhook', [MarketplaceController::class, 'webhook'])
    ->middleware('throttle:60,1')->name('yclients.webhook');
Route::post('/yclients/disconnect', [MarketplaceController::class, 'disconnect'])
    ->middleware('throttle:60,1')->name('yclients.disconnect');
