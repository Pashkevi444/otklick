<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Строгий RLS на crm_connections — содержит чувствительные креды CRM, доступ
 * только в пределах тенанта. Тот же паттерн, что для каналов/базы знаний.
 * Только PostgreSQL; на sqlite (тесты) пропускается.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE crm_connections ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE crm_connections FORCE ROW LEVEL SECURITY');
        DB::statement(
            'CREATE POLICY crm_connections_tenant_isolation ON crm_connections '.
            "USING (tenant_id = current_setting('app.current_tenant', true)::uuid) ".
            "WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS crm_connections_tenant_isolation ON crm_connections');
        DB::statement('ALTER TABLE crm_connections DISABLE ROW LEVEL SECURITY');
    }
};
