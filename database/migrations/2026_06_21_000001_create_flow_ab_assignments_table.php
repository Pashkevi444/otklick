<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A/B-сплит сценариев: какому диалогу какой вариант ветки достался. Конверсию
 * (запись) выводим стыковкой с `conversations.booked_at` — отдельной колонки не
 * держим. Один вариант на диалог в пределах сценария (липкое назначение).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flow_ab_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('flow_id');
            $table->uuid('conversation_id');
            $table->string('variant'); // метка варианта (A/B/…)
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('flow_id')->references('id')->on('flows')->cascadeOnDelete();
            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->unique(['conversation_id', 'flow_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flow_ab_assignments');
    }
};
