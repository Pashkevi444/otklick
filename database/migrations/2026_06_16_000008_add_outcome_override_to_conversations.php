<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ручной итог по лиду, выставленный администратором. Имеет приоритет над
 * автоматически выведенным итогом (Conversation::outcome). null — итог
 * определяется автоматически по состоянию диалога.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('outcome_override')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('outcome_override');
        });
    }
};
