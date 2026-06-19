<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Закрываем «протухшие» открытые диалоги (потерянные лиды) — раз в 5 минут.
Schedule::command('conversations:close-stale')->everyFiveMinutes()->withoutOverlapping();

// Напоминания клиентам о записи (ставятся в очередь) — раз в 5 минут.
Schedule::command('appointments:send-reminders')->everyFiveMinutes()->withoutOverlapping();

// Сверка записей с CRM: закрываем заказы, время визита которых прошло (→ «Успешный
// лид»). Только у тенантов с CRM — раз в час.
Schedule::command('bookings:reconcile')->hourly()->withoutOverlapping();

// Запуск запланированных рассылок (по расписанию/периодичных) — раз в 5 минут.
Schedule::command('broadcasts:run-due')->everyFiveMinutes()->withoutOverlapping();
