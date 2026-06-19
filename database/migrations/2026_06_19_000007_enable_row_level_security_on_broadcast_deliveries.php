<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RLS на broadcast_deliveries — второй рубеж изоляции тенантов (как у broadcasts).
 * Только на PostgreSQL; на sqlite (тесты) пропускается.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE broadcast_deliveries ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE broadcast_deliveries FORCE ROW LEVEL SECURITY');
        DB::statement(
            'CREATE POLICY broadcast_deliveries_tenant_isolation ON broadcast_deliveries '.
            "USING (tenant_id = current_setting('app.current_tenant', true)::uuid) ".
            "WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS broadcast_deliveries_tenant_isolation ON broadcast_deliveries');
        DB::statement('ALTER TABLE broadcast_deliveries DISABLE ROW LEVEL SECURITY');
    }
};
