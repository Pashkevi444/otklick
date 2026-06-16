<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Закрываем «протухшие» открытые диалоги (потерянные лиды) — раз в 5 минут.
Schedule::command('conversations:close-stale')->everyFiveMinutes()->withoutOverlapping();
