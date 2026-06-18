<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RLS на knowledge_gaps — второй рубеж изоляции тенантов (как у knowledge_entries).
 * Применяется только на PostgreSQL; на sqlite (тесты) пропускается.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE knowledge_gaps ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE knowledge_gaps FORCE ROW LEVEL SECURITY');
        DB::statement(
            'CREATE POLICY knowledge_gaps_tenant_isolation ON knowledge_gaps '.
            "USING (tenant_id = current_setting('app.current_tenant', true)::uuid) ".
            "WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS knowledge_gaps_tenant_isolation ON knowledge_gaps');
        DB::statement('ALTER TABLE knowledge_gaps DISABLE ROW LEVEL SECURITY');
    }
};
