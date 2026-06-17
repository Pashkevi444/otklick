<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Поля для напоминаний клиенту о записи: booked_for — дата-время визита (из
 * слота CRM), reminders_sent — какие напоминания (офсеты в минутах) уже
 * отправлены, чтобы не дублировать.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->timestampTz('booked_for')->nullable()->after('crm_record_id');
            $table->json('reminders_sent')->nullable()->after('booked_for');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn(['booked_for', 'reminders_sent']);
        });
    }
};
