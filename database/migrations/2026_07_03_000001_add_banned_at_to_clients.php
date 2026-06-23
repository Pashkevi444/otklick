<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Бан клиента: бизнес может вручную заблокировать абонента. От забаненного бот не
 * ведёт диалог — отвечает фиксированным уведомлением (без LLM). `banned_at` хранит
 * момент блокировки (null = не забанен).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->timestamp('banned_at')->nullable()->after('marketing_opt_out');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn('banned_at');
        });
    }
};
