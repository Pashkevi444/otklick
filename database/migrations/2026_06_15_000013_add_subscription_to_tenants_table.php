<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Подписка тенанта: до какой даты оплачен доступ и ручная блокировка.
 * Кабинет доступен, пока не заблокирован и срок доступа не истёк.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->timestamp('access_expires_at')->nullable()->after('plan');
            $table->boolean('is_blocked')->default(false)->after('access_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['access_expires_at', 'is_blocked']);
        });
    }
};
