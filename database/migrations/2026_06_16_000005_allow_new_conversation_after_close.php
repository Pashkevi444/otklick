<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Разрешаем несколько диалогов на один чат канала во времени: закрытый диалог
 * остаётся в истории, а новое обращение начинает свежий. Жёсткий unique по
 * (channel_id, external_chat_id) заменяем на частичный — он гарантирует, что
 * одновременно открыт только ОДИН незакрытый диалог на чат (защита от гонок),
 * но не мешает заводить новый после закрытия.
 *
 * Частичный unique-индекс одинаково валиден в PostgreSQL и SQLite (тесты).
 */
return new class extends Migration
{
    private const string INDEX = 'conversations_active_chat_unique';

    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropUnique(['channel_id', 'external_chat_id']);
        });

        DB::statement(
            'create unique index '.self::INDEX.
            " on conversations (channel_id, external_chat_id) where status <> 'closed'"
        );
    }

    public function down(): void
    {
        DB::statement('drop index if exists '.self::INDEX);

        Schema::table('conversations', function (Blueprint $table): void {
            $table->unique(['channel_id', 'external_chat_id']);
        });
    }
};
