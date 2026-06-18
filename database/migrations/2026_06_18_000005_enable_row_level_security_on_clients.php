<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RLS на clients — второй рубеж изоляции тенантов (как у knowledge_entries).
 * Применяется только на PostgreSQL; на sqlite (тесты) пропускается.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE clients ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE clients FORCE ROW LEVEL SECURITY');
        DB::statement(
            'CREATE POLICY clients_tenant_isolation ON clients '.
            "USING (tenant_id = current_setting('app.current_tenant', true)::uuid) ".
            "WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS clients_tenant_isolation ON clients');
        DB::statement('ALTER TABLE clients DISABLE ROW LEVEL SECURITY');
    }
};
