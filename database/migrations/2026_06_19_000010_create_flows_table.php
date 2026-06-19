<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Сценарии-воронки (no-code логика бота): владелец сам задаёт «если X → ответь Y,
 * предложи Z». Граф узлов/переходов хранится в `definition` (jsonb): {start, nodes}.
 * Запуск — по ключевым фразам (`triggers`). Тенант-таблица (RLS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flows', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('name');
            $table->boolean('is_active')->default(false);
            $table->jsonb('triggers')->default('[]');
            $table->jsonb('definition')->default('{}');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flows');
    }
};
