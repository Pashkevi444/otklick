<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Состояние плашек дашборда, заданное супер-админом ГЛОБАЛЬНО (для всех бизнесов)
 * — отдельно от прав/тарифов: `new` (новое), `updated` (обновлено), `maintenance`
 * (тех. работы — плашка серая и не открывается). Одна строка на ключ плашки;
 * отсутствие строки = обычное состояние.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_card_states', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('card_key')->unique();
            $table->string('state'); // none | new | updated | maintenance
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_card_states');
    }
};
