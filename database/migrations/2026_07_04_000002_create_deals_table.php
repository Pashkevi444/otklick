<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Сделки — сущность воронки продаж. Создаются вручную или конвертацией из лида.
 * `custom` (jsonb) — значения кастомных полей бизнеса (фильтруемые по GIN). RLS —
 * только pgsql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('client_id')->nullable()->index();
            $table->uuid('stage_id')->index();
            $table->string('title')->nullable();
            $table->integer('value')->nullable(); // сумма сделки, ₽
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->string('source', 16)->default('manual'); // bot|manual
            $table->text('notes')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->jsonb('custom')->nullable(); // значения кастомных полей
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
            $table->foreign('stage_id')->references('id')->on('deal_stages')->cascadeOnDelete();
            $table->foreign('assigned_user_id')->references('id')->on('users')->nullOnDelete();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE deals ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE deals FORCE ROW LEVEL SECURITY');
        DB::statement(
            'CREATE POLICY deals_tenant_isolation ON deals '.
            "USING (tenant_id = current_setting('app.current_tenant', true)::uuid) ".
            "WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
        // GIN-индекс под фильтрацию по кастомным полям.
        DB::statement('CREATE INDEX deals_custom_gin ON deals USING gin (custom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
