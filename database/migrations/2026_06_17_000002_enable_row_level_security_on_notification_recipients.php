<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Строгий RLS на notification_recipients — тот же паттерн, что для остальных
 * тенант-таблиц. Только PostgreSQL; на sqlite (тесты) пропускается.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE notification_recipients ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE notification_recipients FORCE ROW LEVEL SECURITY');
        DB::statement(
            'CREATE POLICY notification_recipients_tenant_isolation ON notification_recipients '.
            "USING (tenant_id = current_setting('app.current_tenant', true)::uuid) ".
            "WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS notification_recipients_tenant_isolation ON notification_recipients');
        DB::statement('ALTER TABLE notification_recipients DISABLE ROW LEVEL SECURITY');
    }
};
