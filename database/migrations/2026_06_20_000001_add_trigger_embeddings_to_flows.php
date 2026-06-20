<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Векторы фраз-триггеров для семантического матчинга сценариев (синонимы:
 * «акция» ≈ «скидка»). Один вектор на триггер; считается при сохранении сценария.
 * Триггеров на тенант единицы — храним jsonb-списком, косинус в PHP (без pgvector).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flows', function (Blueprint $table): void {
            $table->json('trigger_embeddings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('flows', function (Blueprint $table): void {
            $table->dropColumn('trigger_embeddings');
        });
    }
};
