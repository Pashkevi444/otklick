<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Исправление типа `broadcasts.created_by`: id пользователей — bigint, а колонка
 * была ошибочно создана как uuid (на pgsql insert падал с 22P02; sqlite-тесты не
 * ловили из-за нестрогих типов). Колонка пустая (все вставки падали), поэтому
 * безопасно пересоздаём.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table): void {
            $table->dropColumn('created_by');
        });

        Schema::table('broadcasts', function (Blueprint $table): void {
            $table->unsignedBigInteger('created_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table): void {
            $table->dropColumn('created_by');
        });

        Schema::table('broadcasts', function (Blueprint $table): void {
            $table->uuid('created_by')->nullable();
        });
    }
};
