<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Векторный индекс знаний для RAG (Фаза 3): по чанку на запись базы знаний
 * (клиентской и из CRM) с эмбеддингом. На PostgreSQL — колонка pgvector + поиск
 * по оператору `<=>`; на sqlite (тесты) эмбеддинг хранится в JSON, схожесть
 * считается в PHP. Строгий RLS — как у остальных тенант-таблиц (только pgsql).
 */
return new class extends Migration
{
    public function up(): void
    {
        $pgsql = DB::getDriverName() === 'pgsql';
        $dimension = (int) config('services.embedder.dimension');

        if ($pgsql) {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        }

        Schema::create('knowledge_chunks', function (Blueprint $table) use ($pgsql): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('source');            // manual | crm
            $table->uuid('entry_id')->nullable(); // ссылка на запись-источник
            $table->text('content');
            if (! $pgsql) {
                $table->json('embedding')->nullable();
            }
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        if ($pgsql) {
            DB::statement("ALTER TABLE knowledge_chunks ADD COLUMN embedding vector({$dimension})");
            DB::statement('ALTER TABLE knowledge_chunks ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE knowledge_chunks FORCE ROW LEVEL SECURITY');
            DB::statement(
                'CREATE POLICY knowledge_chunks_tenant_isolation ON knowledge_chunks '.
                "USING (tenant_id = current_setting('app.current_tenant', true)::uuid) ".
                "WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};
