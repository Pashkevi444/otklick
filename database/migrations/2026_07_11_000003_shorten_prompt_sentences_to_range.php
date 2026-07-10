<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ещё короче ответы бота: «Отвечай текстом не более 7 предложений» → «Отвечай
 * кратко, 2–4 предложения» в уже созданных промптах ниш (`prompt_templates.body`).
 * Исходник в миграции создания и `PromptBuilder::DEFAULT_BEHAVIOR` уже правлены;
 * существующие записи (в т.ч. правленые СУ) догоняет идемпотентный replace.
 * `replace()` есть и в Postgres, и в sqlite (тесты) — миграция БД-агностична.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('prompt_templates')->update([
            'body' => DB::raw("replace(body, 'текстом не более 7 предложений', 'кратко, 2–4 предложения')"),
        ]);
    }

    public function down(): void
    {
        // Продуктовая правка текста промпта — отката нет.
    }
};
