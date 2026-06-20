<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Нормализация связи лид→клиент: FK conversations.client_id → clients.id
 * ON DELETE SET NULL. Удалили клиента — у лидов ссылка обнуляется (история
 * лида остаётся, «висячих» id не бывает). Только pgsql (sqlite ALTER ADD FK
 * не умеет — в тестах связь чистится на уровне сервиса {@see ClientService::delete}).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // FK не создастся при «сиротах» (client_id удалённых клиентов) — чистим их.
        // conversations под FORCE RLS, поэтому на время очистки снимаем RLS.
        DB::statement('ALTER TABLE conversations DISABLE ROW LEVEL SECURITY');
        DB::statement('UPDATE conversations SET client_id = NULL WHERE client_id IS NOT NULL AND client_id NOT IN (SELECT id FROM clients)');
        DB::statement('ALTER TABLE conversations ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE conversations FORCE ROW LEVEL SECURITY');

        DB::statement('ALTER TABLE conversations ADD CONSTRAINT conversations_client_id_foreign FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE conversations DROP CONSTRAINT IF EXISTS conversations_client_id_foreign');
    }
};
