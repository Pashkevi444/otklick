<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Стадии воронки продаж (per-tenant, настраиваемые). Сделка движется по ним;
 * `kind` помечает выиграно/проиграно. RLS — второй рубеж изоляции (pgsql).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deal_stages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('name');
            $table->string('kind', 16)->default('active'); // active|won|lost
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('color', 24)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE deal_stages ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE deal_stages FORCE ROW LEVEL SECURITY');
        DB::statement(
            'CREATE POLICY deal_stages_tenant_isolation ON deal_stages '.
            "USING (tenant_id = current_setting('app.current_tenant', true)::uuid) ".
            "WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_stages');
    }
};
