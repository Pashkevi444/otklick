<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Прочтение анонсов → ПЕР-ЮЗЕР (а не пер-тенант): у каждого сотрудника своё
 * «прочитано». Добавляем user_id и переключаем уникальность с (анонс, тенант) на
 * (анонс, пользователь). Старые пер-тенантные строки (user_id=null) перестают
 * считаться прочтением конкретного юзера — новость один раз снова станет
 * непрочитанной (приемлемо).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcement_reads', function (Blueprint $table): void {
            $table->uuid('user_id')->nullable()->after('tenant_id');
            $table->index('user_id');
        });

        Schema::table('announcement_reads', function (Blueprint $table): void {
            $table->dropUnique(['announcement_id', 'tenant_id']);
            $table->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('announcement_reads', function (Blueprint $table): void {
            $table->dropUnique(['announcement_id', 'user_id']);
            $table->unique(['announcement_id', 'tenant_id']);
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
