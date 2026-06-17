<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Состояние пошаговой записи в CRM (BookingFlow): на каком шаге диалог
 * (услуга/мастер/день/время/телефон) и накопленный выбор. null — активной
 * записи нет, диалог ведёт обычный бот.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->json('booking_state')->nullable()->after('outcome_override');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('booking_state');
        });
    }
};
