<?php

declare(strict_types=1);

use App\Shared\Tenancy\SandboxScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Реестр «песочницы»: помечает строки основных таблиц (диалоги, клиенты, каналы
 * и т.п.), созданные в режиме тестирования бота, как тестовые — чтобы они НЕ
 * засоряли бизнес-выборки и удалялись планировщиком раз в сутки.
 *
 * Признак «тест» вынесен в отдельную таблицу-маркер: основные таблицы схему не
 * меняют. recordable_type — имя таблицы помеченной строки, recordable_id — её id.
 * Глобальный scope {@see SandboxScope} прячет помеченные строки вне
 * режима теста; команда `sandbox:purge` чистит их вместе с этим реестром.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sandbox_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('recordable_type'); // имя таблицы (conversations/clients/…)
            $table->uuid('recordable_id');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['recordable_type', 'recordable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sandbox_records');
    }
};
