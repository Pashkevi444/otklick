<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Получатели уведомлений владельца о событиях (новый лид, нужен человек, запись).
 * Привязаны к бизнесу; канал — email или telegram (расширяемо). Для Telegram
 * привязка идёт по диплинку: до подтверждения value пуст, хранится link_token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_recipients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('type');                 // email | telegram
            $table->string('value')->nullable();    // адрес почты / telegram chat_id
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('link_token')->nullable()->index(); // для привязки Telegram
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_recipients');
    }
};
