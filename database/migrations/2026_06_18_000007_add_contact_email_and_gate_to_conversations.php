<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Контактная форма в начале диалога (ContactGate): email клиента (необязателен)
 * и флаг, что форма уже отработала (имя+телефон собраны или клиент узнан) —
 * чтобы не показывать её повторно.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('contact_email')->nullable()->after('contact_phone');
            $table->boolean('contacts_gate_done')->default(false)->after('contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn(['contact_email', 'contacts_gate_done']);
        });
    }
};
