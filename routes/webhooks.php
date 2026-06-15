<?php

declare(strict_types=1);

use App\Http\Controllers\Webhooks\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

/*
 * Входящие вебхуки каналов. Stateless (без сессий и CSRF) — внешние сервисы их
 * не присылают. Источник верифицируется в контроллере (secret-токен).
 */
Route::post('/webhooks/telegram/{tenant}/{channel}', TelegramWebhookController::class)
    ->name('webhooks.telegram');
