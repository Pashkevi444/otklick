<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RLS на sandbox_records — второй рубеж изоляции тенантов (как у остальных
 * тенант-таблиц). Только на PostgreSQL; на sqlite (тесты) пропускается.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE sandbox_records ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE sandbox_records FORCE ROW LEVEL SECURITY');
        DB::statement(
            'CREATE POLICY sandbox_records_tenant_isolation ON sandbox_records '.
            "USING (tenant_id = current_setting('app.current_tenant', true)::uuid) ".
            "WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS sandbox_records_tenant_isolation ON sandbox_records');
        DB::statement('ALTER TABLE sandbox_records DISABLE ROW LEVEL SECURITY');
    }
};
