<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * База клиентов бизнеса: единая карточка клиента, к которой привязываются лиды
 * (диалоги). Идентичность — по телефону в пределах тенанта. Кроме базовых полей
 * (имя/телефон) есть запас (email, ник Telegram, краткое резюме от LLM, заметки)
 * — на случай, если бизнес «переобучит» бота вытаскивать больше данных.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('telegram_username')->nullable();
            $table->string('first_channel_type')->nullable(); // откуда пришёл впервые
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->text('summary')->nullable();               // краткое резюме (LLM)
            $table->timestamp('summary_generated_at')->nullable();
            $table->text('notes')->nullable();                 // заметки бизнеса вручную
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            // Один клиент на телефон в пределах тенанта (ключ идентичности).
            $table->unique(['tenant_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
