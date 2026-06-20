<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Финал нормализации лид↔клиент: имя/телефон/email — атрибуты человека, живут
 * ТОЛЬКО в карточке клиента (`clients`). Буфер `conversations.contact_*` больше
 * не пишется и не читается (всё через `ClientService` / `Conversation::display*`)
 * — удаляем. `contact_ref` (ссылка на аккаунт/IP треда) и `contacts_gate_done`
 * остаются: это свойства самого диалога.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn(['contact_name', 'contact_phone', 'contact_email']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
        });
    }
};
