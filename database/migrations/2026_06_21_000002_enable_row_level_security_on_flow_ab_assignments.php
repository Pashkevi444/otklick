<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RLS на flow_ab_assignments — второй рубеж изоляции тенантов (как у flows).
 * Только на PostgreSQL; на sqlite (тесты) пропускается.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE flow_ab_assignments ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE flow_ab_assignments FORCE ROW LEVEL SECURITY');
        DB::statement(
            'CREATE POLICY flow_ab_assignments_tenant_isolation ON flow_ab_assignments '.
            "USING (tenant_id = current_setting('app.current_tenant', true)::uuid) ".
            "WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS flow_ab_assignments_tenant_isolation ON flow_ab_assignments');
        DB::statement('ALTER TABLE flow_ab_assignments DISABLE ROW LEVEL SECURITY');
    }
};
