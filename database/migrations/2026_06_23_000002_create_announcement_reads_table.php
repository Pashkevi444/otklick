<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Отметка «бизнес прочитал анонс» (пер-тенант): по ней считаем непрочитанное и
 * подсвечиваем пункт меню. Один факт прочтения на (анонс, тенант).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_reads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('announcement_id');
            $table->uuid('tenant_id')->index();
            $table->timestamp('read_at');
            $table->timestamps();

            $table->foreign('announcement_id')->references('id')->on('announcements')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['announcement_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
    }
};
