<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Момент оформления записи в диалоге (сигнал [[BOOKED]] от бота). Нужен для
 * аналитики конверсии лидов: сколько обращений довели до записи. null — записи
 * не было.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->timestamp('booked_at')->nullable()->after('clarification_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('booked_at');
        });
    }
};
