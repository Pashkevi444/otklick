<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Идентификатор записи в CRM (record_id от YClients), созданной ботом. Нужен,
 * чтобы позже отменить запись в CRM по просьбе клиента. null — записи нет/отменена.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('crm_record_id')->nullable()->after('booking_state');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('crm_record_id');
        });
    }
};
