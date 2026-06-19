<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Разделение получателей уведомлений: роль (director|staff) и подписка на типы
 * событий (events: список OwnerEvent; [] = все). Существующие получатели — все
 * директора со всеми типами (как было).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_recipients', function (Blueprint $table): void {
            $table->string('role')->default('director');
            $table->jsonb('events')->default('[]');
        });
    }

    public function down(): void
    {
        Schema::table('notification_recipients', function (Blueprint $table): void {
            $table->dropColumn(['role', 'events']);
        });
    }
};
