<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Лиды — входящие обращения (из диалога бота по контактной форме или вручную).
 * Конвертируются в сделку (`deal_id`). `custom` (jsonb) — кастомные поля бизнеса
 * (фильтруемые по GIN). Один лид на диалог — partial-unique (NULL не конфликтуют).
 * RLS — только pgsql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('client_id')->nullable()->index();
            $table->uuid('conversation_id')->nullable();
            $table->uuid('deal_id')->nullable()->index();
            $table->string('status', 16)->default('new'); // new|working|converted|dismissed
            $table->string('source', 16)->default('manual'); // bot|manual
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('custom')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
            $table->foreign('conversation_id')->references('id')->on('conversations')->nullOnDelete();
            $table->foreign('deal_id')->references('id')->on('deals')->nullOnDelete();
            $table->unique(['tenant_id', 'conversation_id']);
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE leads ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE leads FORCE ROW LEVEL SECURITY');
        DB::statement(
            'CREATE POLICY leads_tenant_isolation ON leads '.
            "USING (tenant_id = current_setting('app.current_tenant', true)::uuid) ".
            "WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
        DB::statement('CREATE INDEX leads_custom_gin ON leads USING gin (custom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
