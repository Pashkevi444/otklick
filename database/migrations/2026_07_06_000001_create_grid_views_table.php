<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Сохранённые «виды» универсального грида (колонки/фильтры/сортировка) — личные,
 * на пользователя, по сущности (deal|lead|client|conversation). `config` jsonb.
 * RLS — только pgsql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grid_views', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('entity', 16); // deal|lead|client|conversation
            $table->string('name');
            $table->jsonb('config'); // {columns:[], filters:[], sort:{}|null}
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE grid_views ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE grid_views FORCE ROW LEVEL SECURITY');
        DB::statement(
            'CREATE POLICY grid_views_tenant_isolation ON grid_views '.
            "USING (tenant_id = current_setting('app.current_tenant', true)::uuid) ".
            "WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('grid_views');
    }
};
