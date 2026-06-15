<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Распространяет Row-Level Security (второй, жёсткий рубеж изоляции тенантов)
 * на messaging-таблицы — тот же паттерн, что и для users в
 * 2026_06_13_000003_enable_row_level_security.php.
 *
 * Применяется только на PostgreSQL. Политика фильтрует строки по сессионной
 * переменной app.current_tenant, которую выставляет App\Tenancy\TenantInitializer.
 * На sqlite (тесты) пропускается; там изоляцию держит глобальный TenantScope.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const array TENANT_TABLES = ['channels', 'conversations', 'messages'];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TENANT_TABLES as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement(
                "CREATE POLICY {$table}_tenant_isolation ON {$table} ".
                "USING (tenant_id = current_setting('app.current_tenant', true)::uuid) ".
                "WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::TENANT_TABLES as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
