<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Юридические реквизиты владельца площадки (ИП) для футера сайта и оферты:
 * наименование, ИНН, ОГРНИП. Редактируются супер-админом.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('legal_name')->nullable()->after('telegram');
            $table->string('inn', 20)->nullable()->after('legal_name');
            $table->string('ogrnip', 20)->nullable()->after('inn');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn(['legal_name', 'inn', 'ogrnip']);
        });
    }
};
