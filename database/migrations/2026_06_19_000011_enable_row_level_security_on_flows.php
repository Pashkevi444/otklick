<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RLS на flows — второй рубеж изоляции тенантов (как у clients/broadcasts).
 * Только на PostgreSQL; на sqlite (тесты) пропускается.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE flows ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE flows FORCE ROW LEVEL SECURITY');
        DB::statement(
            'CREATE POLICY flows_tenant_isolation ON flows '.
            "USING (tenant_id = current_setting('app.current_tenant', true)::uuid) ".
            "WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS flows_tenant_isolation ON flows');
        DB::statement('ALTER TABLE flows DISABLE ROW LEVEL SECURITY');
    }
};
