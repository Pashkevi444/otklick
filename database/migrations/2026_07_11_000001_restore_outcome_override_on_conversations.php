<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Восстанавливает колонку `conversations.outcome_override` — ручной итог лида,
 * выставляемый владельцем в журнале (см. ConversationController::setStatus,
 * Conversation::outcome). На проде её удалила осиротевшая миграция
 * `drop_outcome_override_from_conversations` (фичу откатили, а саму миграцию —
 * нет), и смена статуса лида падала с SQL-ошибкой «column does not exist».
 *
 * Идемпотентно: добавляет колонку только если её нет (на свежей БД она уже создана
 * миграцией `add_outcome_override_to_conversations`, поэтому шаг пропускается).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('conversations', 'outcome_override')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->string('outcome_override')->nullable()->after('cancelled_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('conversations', 'outcome_override')) {
            Schema::table('conversations', function (Blueprint $table): void {
                $table->dropColumn('outcome_override');
            });
        }
    }
};
