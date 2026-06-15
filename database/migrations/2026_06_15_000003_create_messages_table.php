<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Сообщение диалога. Уникальность (conversation_id, direction, external_message_id)
 * обеспечивает идемпотентность при ретраях вебхука: повторная доставка того же
 * сообщения канала не плодит дублей.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('conversation_id')->index();
            $table->string('direction');
            $table->string('external_message_id')->nullable();
            $table->text('text')->nullable();
            $table->jsonb('payload')->nullable();
            $table->string('status');
            $table->timestamps();

            $table->unique(['conversation_id', 'direction', 'external_message_id']);

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('conversation_id')
                ->references('id')->on('conversations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
