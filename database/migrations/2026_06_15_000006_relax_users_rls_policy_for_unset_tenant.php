<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Переводит RLS-полиси таблицы users на «разрешающую при невыставленном тенанте»
 * форму.
 *
 * users — таблица идентичности и бутстрапа аутентификации (как tenants —
 * реестр): её читают по email/id ДО того, как известен тенант (логин, загрузка
 * сессии), а супер-админ вообще не привязан к тенанту (tenant_id = null).
 * Строгая полиси (tenant_id = current_setting('app.current_tenant')) сделала бы
 * аутентификацию невозможной под FORCE RLS.
 *
 * Новая полиси совпадает по семантике с App\Shared\Tenancy\TenantScope: контекст не
 * задан → строки видны (бутстрап/консоль/супер-админ); контекст задан → жёсткая
 * изоляция по tenant_id (обычные запросы тенант-пользователей). Бизнес-таблицы
 * (channels/conversations/messages/knowledge_entries) остаются строгими — к ним
 * всегда обращаются с заданным контекстом.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS users_tenant_isolation ON users');
        DB::statement(
            'CREATE POLICY users_tenant_isolation ON users '.
            "USING (current_setting('app.current_tenant', true) IS NULL ".
            "OR current_setting('app.current_tenant', true) = '' ".
            "OR tenant_id = current_setting('app.current_tenant', true)::uuid) ".
            "WITH CHECK (current_setting('app.current_tenant', true) IS NULL ".
            "OR current_setting('app.current_tenant', true) = '' ".
            "OR tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP POLICY IF EXISTS users_tenant_isolation ON users');
        DB::statement(
            'CREATE POLICY users_tenant_isolation ON users '.
            "USING (tenant_id = current_setting('app.current_tenant', true)::uuid) ".
            "WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
    }
};
