<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Подзаголовок главной был `varchar(255)` — длинного маркетингового описания
 * (несколько предложений) туда не влезает, сохранение падало. Делаем `text`,
 * как у `access_note`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->text('hero_subtitle')->change();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('hero_subtitle')->change();
        });
    }
};
