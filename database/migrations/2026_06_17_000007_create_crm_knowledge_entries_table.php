<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * База знаний из CRM — нередактируемые «сервисные» записи (услуги, мастера,
 * филиал), выгружаемые фоновой задачей из системы записи. Отдельно от
 * клиентской базы знаний (knowledge_entries), которую ведёт сам бизнес.
 * При коллизии данные из CRM считаются приоритетными (актуальнее).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_knowledge_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('category');           // service | staff | company
            $table->string('external_id')->nullable(); // id сущности в CRM
            $table->string('title');
            $table->text('content');
            $table->json('meta')->nullable();     // цена, длительность, специализация и т.п.
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_knowledge_entries');
    }
};
