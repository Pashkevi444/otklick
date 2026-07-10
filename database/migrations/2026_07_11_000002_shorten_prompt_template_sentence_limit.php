<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Укорачиваем ответы бота: лимит «не более 15 предложений» → «не более 7» в уже
 * созданных промптах ниш (`prompt_templates.body`). Исходник промпта в миграции
 * создания уже правлен на «7», но существующие записи (в т.ч. отредактированные
 * СУ) она не перезапишет — точечный идемпотентный replace догоняет их на деплое.
 * `replace()` есть и в Postgres, и в sqlite (тесты) — миграция БД-агностична.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('prompt_templates')->update([
            'body' => DB::raw("replace(body, 'не более 15 предложений', 'не более 7 предложений')"),
        ]);
    }

    public function down(): void
    {
        // Продуктовая правка текста промпта — отката нет.
    }
};
