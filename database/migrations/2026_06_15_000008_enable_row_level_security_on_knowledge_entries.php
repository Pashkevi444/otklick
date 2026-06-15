<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Строгий RLS на knowledge_entries (бизнес-данные всегда читаются/пишутся с
 * заданным тенант-контекстом) — тот же паттерн, что для messaging-таблиц.
 * Только PostgreSQL; на sqlite (тесты) пропускается.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE knowledge_entries ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE knowledge_entries FORCE ROW LEVEL SECURITY');
        DB::statement(
            'CREATE POLICY knowledge_entries_tenant_isolation ON knowledge_entries '.
            "USING (tenant_id = current_setting('app.current_tenant', true)::uuid) ".
            "WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS knowledge_entries_tenant_isolation ON knowledge_entries');
        DB::statement('ALTER TABLE knowledge_entries DISABLE ROW LEVEL SECURITY');
    }
};
