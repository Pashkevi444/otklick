<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Перехват диалога оператором (живой чат): пока `operator_active_at` свежий —
 * диалогом управляет человек, а не бот. `operator_user_id` — кто перехватил.
 */
return new class extends Migration
{
    public function up(): void
    {
        // FK на уровне БД НЕ ставим: добавление внешнего ключа через ALTER на
        // sqlite (тесты) пересоздаёт таблицу и ломает частичный unique-индекс
        // диалогов. Целостность обеспечивает приложение (id берём из авторизации),
        // а удалённый оператор → связь operator() просто вернёт null.
        Schema::table('conversations', function (Blueprint $table): void {
            $table->timestamp('operator_active_at')->nullable()->index();
            $table->unsignedBigInteger('operator_user_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn(['operator_active_at', 'operator_user_id']);
        });
    }
};
