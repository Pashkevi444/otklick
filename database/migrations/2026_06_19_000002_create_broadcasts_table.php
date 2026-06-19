<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Рассылки по базе клиентов: одно сообщение → выбранные каналы (мессенджеры +
 * почта). Запускается вручную или по расписанию (next_run_at + recurrence);
 * планировщик находит «созревшие» и ставит в очередь. Счётчики доставки —
 * агрегатом на самой рассылке.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('title');
            $table->text('body');
            // Список целей: подмножество {telegram, vk, max, email}.
            $table->jsonb('channels')->default('[]');
            $table->string('status')->default('draft');
            $table->string('recurrence')->default('none');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
