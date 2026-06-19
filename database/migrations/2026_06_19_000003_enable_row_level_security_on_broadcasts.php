<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RLS на broadcasts — второй рубеж изоляции тенантов (как у clients).
 * Применяется только на PostgreSQL; на sqlite (тесты) пропускается.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE broadcasts ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE broadcasts FORCE ROW LEVEL SECURITY');
        DB::statement(
            'CREATE POLICY broadcasts_tenant_isolation ON broadcasts '.
            "USING (tenant_id = current_setting('app.current_tenant', true)::uuid) ".
            "WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS broadcasts_tenant_isolation ON broadcasts');
        DB::statement('ALTER TABLE broadcasts DISABLE ROW LEVEL SECURITY');
    }
};
