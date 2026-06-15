<?php

declare(strict_types=1);

use App\Enums\ConversationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Диалог с конкретным клиентом в рамках канала. Один external_chat_id канала —
 * один диалог.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('channel_id')->index();
            $table->string('external_chat_id');
            $table->string('contact_name')->nullable();
            $table->string('status')->default(ConversationStatus::default()->value);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['channel_id', 'external_chat_id']);

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('channel_id')
                ->references('id')->on('channels')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
