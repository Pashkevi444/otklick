<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Промежуточная привязка подключения из маркетплейса YClients. Намеренно НЕ
 * тенант-таблица: вебхук YClients приходит без тенант-контекста (server-to-server),
 * поэтому факты привязываем к salon_id, а к тенанту — отдельным шагом, когда бизнес
 * возвращается на Registration Redirect уже залогиненным. Как только известны и
 * тенант, и user-токен — материализуем рабочее подключение в crm_connections.
 *
 * user_token шифруется (cast `encrypted` в модели). RLS здесь не нужен — строки не
 * принадлежат тенанту; доступ к таблице только из доверенного кода.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yclients_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('salon_id')->unique();
            $table->uuid('tenant_id')->nullable()->index();
            $table->text('user_token')->nullable();
            $table->jsonb('raw')->default('{}');
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yclients_links');
    }
};
