<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Снимок ценности оформленной ботом записи — для «Отчёта ценности» в аналитике.
 *
 * Атрибуция по CRM: `crm_connection_id` фиксирует, в какую CRM ушла запись (у
 * тенанта может быть несколько подключений → отдельный отчёт на каждую). Цену и
 * услугу снимаем в момент записи (`booked_service_*`), чтобы последующая
 * переоценка услуг в CRM не искажала исторические суммы — выручка считается
 * «точно по записям».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->uuid('crm_connection_id')->nullable()->index()->after('crm_record_id');
            $table->string('booked_service_id')->nullable()->after('crm_connection_id');
            $table->string('booked_service_title')->nullable()->after('booked_service_id');
            // Цена услуги в рублях на момент записи (YClients price_min — целое).
            $table->integer('booked_service_price')->nullable()->after('booked_service_title');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn([
                'crm_connection_id',
                'booked_service_id',
                'booked_service_title',
                'booked_service_price',
            ]);
        });
    }
};
