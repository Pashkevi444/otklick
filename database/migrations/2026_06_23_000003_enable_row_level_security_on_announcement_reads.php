<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RLS на announcement_reads — отметки прочтения скоупятся тенантом (как прочие
 * тенант-таблицы). Только на PostgreSQL; на sqlite (тесты) пропускается.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE announcement_reads ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE announcement_reads FORCE ROW LEVEL SECURITY');
        DB::statement(
            'CREATE POLICY announcement_reads_tenant_isolation ON announcement_reads '.
            "USING (tenant_id = current_setting('app.current_tenant', true)::uuid) ".
            "WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS announcement_reads_tenant_isolation ON announcement_reads');
        DB::statement('ALTER TABLE announcement_reads DISABLE ROW LEVEL SECURITY');
    }
};
