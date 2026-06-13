<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Pgvector\Laravel\Schema as PgvectorSchema;

/**
 * Регистрирует Blueprint-макросы pgvector (vector-колонки для RAG в Фазе 2).
 *
 * Заменяет авто-дискавери пакета (отключён в composer.json), чтобы НЕ загружать
 * его миграцию `CREATE EXTENSION vector` на не-PostgreSQL соединениях (sqlite в
 * тестах). Само расширение включается нашей pgsql-guarded миграцией.
 */
final class PgvectorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        PgvectorSchema::register();
    }
}
