<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RLS на client_identities — второй рубеж изоляции тенантов (как у clients).
 * Только на PostgreSQL; на sqlite (тесты) пропускается.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE client_identities ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE client_identities FORCE ROW LEVEL SECURITY');
        DB::statement(
            'CREATE POLICY client_identities_tenant_isolation ON client_identities '.
            "USING (tenant_id = current_setting('app.current_tenant', true)::uuid) ".
            "WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS client_identities_tenant_isolation ON client_identities');
        DB::statement('ALTER TABLE client_identities DISABLE ROW LEVEL SECURITY');
    }
};
