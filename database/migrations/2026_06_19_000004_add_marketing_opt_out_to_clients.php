<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Отписка клиента от рассылок (152-ФЗ: уважаем отказ). По умолчанию false —
 * клиент в базе попадает в аудиторию, пока явно не отписан. Ответственность за
 * правовое основание рассылки несёт бизнес.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->boolean('marketing_opt_out')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn('marketing_opt_out');
        });
    }
};
