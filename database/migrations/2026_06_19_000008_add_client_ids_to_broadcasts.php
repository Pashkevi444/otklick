<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Выбор получателей рассылки: список id клиентов. NULL/пусто — вся база (без
 * отписавшихся). Отписка (marketing_opt_out) уважается даже для явно выбранных.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table): void {
            $table->jsonb('client_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table): void {
            $table->dropColumn('client_ids');
        });
    }
};
