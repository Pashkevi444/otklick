<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Счётчик подряд идущих уточняющих вопросов бота. Пока бот переспрашивает
 * (не нашёл ответ в базе / вопрос неясен), счётчик растёт; на лимите диалог
 * эскалируется на администратора. Обнуляется, как только бот ответил по делу.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->unsignedSmallInteger('clarification_attempts')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('clarification_attempts');
        });
    }
};
