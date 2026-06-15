<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Переименование тарифов: starter → standard (Стандарт), pro → max (Макс).
 * Пробный (trial) не меняется.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tenants')->where('plan', 'starter')->update(['plan' => 'standard']);
        DB::table('tenants')->where('plan', 'pro')->update(['plan' => 'max']);
    }

    public function down(): void
    {
        DB::table('tenants')->where('plan', 'standard')->update(['plan' => 'starter']);
        DB::table('tenants')->where('plan', 'max')->update(['plan' => 'pro']);
    }
};
