<?php

declare(strict_types=1);

use App\Enums\BusinessType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Справочник типов бизнеса (`business_types`) — отдельная таблица вместо «жёсткого»
 * enum: СУ/будущая регистрация смогут назначать тип тенанту, а шаблоны/БЗ
 * фильтруются по нему. Начальный набор засеян из {@see BusinessType}. Тенант
 * получает колонку `business_type` (ключ справочника, null = не задан).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_types', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('label');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        foreach (BusinessType::cases() as $i => $case) {
            DB::table('business_types')->updateOrInsert(['key' => $case->value], [
                'label' => $case->label(),
                'sort_order' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Тип бизнеса тенанта — без FK (sqlite в тестах не любит ALTER+FK на
        // существующей таблице); целостность поддерживаем валидацией по справочнику.
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('business_type')->nullable()->after('slug')->index();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('business_type');
        });

        Schema::dropIfExists('business_types');
    }
};
