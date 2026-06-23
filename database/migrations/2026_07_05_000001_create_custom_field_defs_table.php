<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Определения кастомных полей бизнеса для лидов и сделок. Значения хранятся в
 * jsonb-колонке `custom` самих лидов/сделок; здесь — только схема полей
 * (ключ/подпись/тип/опции). RLS — только pgsql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_defs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('entity', 8);  // lead|deal
            $table->string('key', 64);     // стабильный ключ в jsonb `custom`
            $table->string('label');
            $table->string('type', 16);    // text|number|select|date|bool
            $table->jsonb('options')->nullable(); // варианты для select
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'entity', 'key']);
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE custom_field_defs ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE custom_field_defs FORCE ROW LEVEL SECURITY');
        DB::statement(
            'CREATE POLICY custom_field_defs_tenant_isolation ON custom_field_defs '.
            "USING (tenant_id = current_setting('app.current_tenant', true)::uuid) ".
            "WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_defs');
    }
};
