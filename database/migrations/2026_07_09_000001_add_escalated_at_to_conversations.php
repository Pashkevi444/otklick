<?php

declare(strict_types=1);

use App\Enums\ConversationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Метка эскалации диалога на оператора — чистый сигнал «бот не справился» для
 * аналитики (качество бота), независимый от статуса (который убираем). Бэкфилл:
 * у диалогов в статусе needs_human проставляем метку по времени обновления.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->timestamp('escalated_at')->nullable()->after('booked_at');
        });

        // Бэкфилл существующих эскалаций (пока статус ещё есть).
        DB::table('conversations')
            ->where('status', ConversationStatus::NeedsHuman->value)
            ->whereNull('escalated_at')
            ->update(['escalated_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('escalated_at');
        });
    }
};
