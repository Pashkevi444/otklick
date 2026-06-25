<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Согласие на обработку персональных данных (152-ФЗ): флаг + момент получения.
 * Бот не ведёт диалог, пока клиент не подтвердил согласие (ConsentGate); в
 * веб-виджете согласие даётся галочкой при первом открытии. Момент фиксируем для
 * аудита (когда именно клиент согласился).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->boolean('consent_agreed')->default(false)->after('contacts_gate_done');
            $table->timestamp('consent_agreed_at')->nullable()->after('consent_agreed');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn(['consent_agreed', 'consent_agreed_at']);
        });
    }
};
