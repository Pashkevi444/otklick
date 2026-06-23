<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Убираем ручной «итог лида» (outcome_override) — воронка и её статусы теперь
 * живут в сделках, а диалог стал просто логом переписки. Сигналы для аналитики
 * остаются как факты: booked_at / cancelled_at / escalated_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('outcome_override');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('outcome_override', 16)->nullable()->after('cancelled_at');
        });
    }
};
