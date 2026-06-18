<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Пробелы бота»: реальные вопросы клиентов, на которые бот не смог дать ответ
 * (эскалация из-за отсутствия ответа в базе знаний). Бизнес видит их в кабинете
 * и пополняет базу знаний — бот «умнеет». Дедуп по нормализованному вопросу в
 * пределах тенанта (`occurrences` — сколько раз спрашивали).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_gaps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->text('question');
            $table->string('normalized')->index(); // для дедупа в пределах тенанта
            $table->unsignedInteger('occurrences')->default(1);
            $table->uuid('conversation_id')->nullable();
            $table->string('channel_type')->nullable();
            $table->string('status')->default('open')->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_gaps');
    }
};
