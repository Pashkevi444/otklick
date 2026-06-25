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
// Оформление виджета (цвет акцента) — читает рантайм при загрузке, до сессии.
Route::get('/widget/v1/{tenant}/{channel}/config', [WidgetChatController::class, 'config'])
    ->middleware('throttle:60,1')->name('widget.config');
Route::options('/widget/v1/{tenant}/{channel}/config', [WidgetChatController::class, 'preflight']);
Route::options('/widget/v1/{tenant}/{channel}/session', [WidgetChatController::class, 'preflight']);
Route::options('/widget/v1/{tenant}/{channel}/message', [WidgetChatController::class, 'preflight']);
Route::options('/widget/v1/{tenant}/{channel}/upload', [WidgetChatController::class, 'preflight']);
Route::options('/widget/v1/{tenant}/{channel}/poll', [WidgetChatController::class, 'preflight']);
Route::post('/widget/v1/{tenant}/{channel}/session', [WidgetChatController::class, 'session'])
    ->middleware('throttle:20,1')->name('widget.session');
Route::post('/widget/v1/{tenant}/{channel}/message', [WidgetChatController::class, 'message'])
    ->middleware('throttle:40,1')->name('widget.message');
// Загрузка фото клиентом (бот его не распознаёт — диалог уходит администратору).
Route::post('/widget/v1/{tenant}/{channel}/upload', [WidgetChatController::class, 'upload'])
    ->middleware('throttle:20,1')->name('widget.upload');
// Лайв-поллинг (раз в ~3 сек): ответы оператора + статус «оператор на связи».
Route::post('/widget/v1/{tenant}/{channel}/poll', [WidgetChatController::class, 'poll'])
    ->middleware('throttle:120,1')->name('widget.poll');

/*
 * YClients Marketplace: server-to-server вебхуки. Stateless (без сессий/CSRF).
 * Подлинность — по партнёрскому токену в теле. Registration Redirect (привязка
 * филиала к тенанту) — авторизованный роут /yclients/connect в routes/web.php.
 */
Route::post('/yclients/webhook', [MarketplaceController::class, 'webhook'])
    ->middleware('throttle:60,1')->name('yclients.webhook');
Route::post('/yclients/disconnect', [MarketplaceController::class, 'disconnect'])
    ->middleware('throttle:60,1')->name('yclients.disconnect');
