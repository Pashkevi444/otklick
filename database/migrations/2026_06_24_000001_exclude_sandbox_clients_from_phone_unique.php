<?php

declare(strict_types=1);

use App\Models\Concerns\MarksSandbox;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Тестовые клиенты «песочницы» (тестирование бота) не должны конфликтовать по
 * уникальности телефона с настоящими: тестер может ввести телефон, совпавший с
 * реальным клиентом, а в режиме теста реальные строки не видны (изоляция) — и
 * вставка падала на глобальном `clients_tenant_id_phone_unique`.
 *
 * Решение: пометка `is_test` на строке + ЧАСТИЧНЫЙ unique-индекс только для
 * НЕ-тестовых клиентов (`WHERE is_test = false`). Реальные клиенты по-прежнему
 * уникальны по телефону; тестовые из ограничения исключены. Признак ставится в
 * момент вставки (см. {@see MarksSandbox}); видимость/чистку
 * по-прежнему ведёт реестр sandbox_records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->boolean('is_test')->default(false);
            $table->dropUnique('clients_tenant_id_phone_unique');
        });

        $cond = DB::getDriverName() === 'pgsql' ? 'false' : '0';
        DB::statement("CREATE UNIQUE INDEX clients_tenant_id_phone_unique ON clients (tenant_id, phone) WHERE is_test = {$cond}");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS clients_tenant_id_phone_unique');

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn('is_test');
            $table->unique(['tenant_id', 'phone']);
        });
    }
};
