<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Внешняя «привязка» контакта для деталей диалога: для мессенджеров — ссылка на
 * аккаунт клиента (например, https://t.me/username), для веб-виджета — IP
 * посетителя. Хранится отдельно от имени: имя — то, как клиент представился сам.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('contact_ref')->nullable()->after('contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('contact_ref');
        });
    }
};
