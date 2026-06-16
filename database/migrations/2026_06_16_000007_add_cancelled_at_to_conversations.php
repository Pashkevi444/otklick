<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Момент отмены записи клиентом в диалоге (сигнал [[CANCELLED]] от бота). Нужен
 * для итога лида «Отменён клиентом». null — отмены не было.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->timestamp('cancelled_at')->nullable()->after('booked_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('cancelled_at');
        });
    }
};
