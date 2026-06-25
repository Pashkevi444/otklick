<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * In-app уведомления (колокольчик + бейджи плашек). Фан-аут: на каждое событие
 * создаётся строка для КАЖДОГО пользователя тенанта, у кого есть право видеть тип
 * (матрица доступа) — поэтому read-state пер-юзер, а изоляция тенантов — RLS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->unsignedBigInteger('user_id'); // получатель (users.id — bigint)
            $table->string('type');
            // Сущность-источник: открытие/просмотр именно ЕЁ гасит уведомление
            // (пер-элемент, а не «весь раздел при заходе» — у нас пагинация).
            $table->string('entity_type')->nullable(); // conversation | client | gap
            $table->uuid('entity_id')->nullable();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('url')->nullable(); // ссылка для перехода из колокольчика
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'entity_type', 'entity_id']);
        });

        // RLS — второй рубеж изоляции тенантов (только PostgreSQL; на sqlite пропуск).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE user_notifications ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE user_notifications FORCE ROW LEVEL SECURITY');
            DB::statement(
                'CREATE POLICY user_notifications_tenant_isolation ON user_notifications '.
                "USING (tenant_id = current_setting('app.current_tenant', true)::uuid) ".
                "WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
