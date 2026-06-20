<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Анонсы площадки: новости и обновления (патчи). Публикует супер-админ, видны
 * всем бизнесам — поэтому глобальные (без tenant_id, как site_settings). Тип
 * разводит две ленты (`news` / `update`); механизм общий.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type'); // news | update
            $table->string('title');
            $table->text('body');
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_published', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
