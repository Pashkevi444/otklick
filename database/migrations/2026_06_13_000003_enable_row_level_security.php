<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Row-Level Security как второй (жёсткий) рубеж изоляции тенантов на уровне БД.
 *
 * Применяется только на PostgreSQL. Политика фильтрует строки по сессионной
 * переменной app.current_tenant, которую приложение выставляет в начале каждого
 * запроса (middleware резолва тенанта — Фаза 1). На sqlite (тесты) пропускается;
 * там изоляцию обеспечивает глобальный scope (TenantScope).
 */
return new class extends Migration
{
    /** Тенант-таблицы, на которые навешивается RLS. */
    private const array TENANT_TABLES = ['users'];

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
