<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Богатый контент записи базы знаний: ссылки и картинки-«примеры работ».
 * Структурированные поля (jsonb-массивы) — без отдельных таблиц.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_entries', function (Blueprint $table): void {
            // links: [{label, url}]
            $table->jsonb('links')->default('[]');
            // images: [{path, url}] — path на public-диске, url для показа
            $table->jsonb('images')->default('[]');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_entries', function (Blueprint $table): void {
            $table->dropColumn(['links', 'images']);
        });
    }
};
