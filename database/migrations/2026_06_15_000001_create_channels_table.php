<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Канал общения тенанта (Telegram / WhatsApp / веб-виджет). Через канал
 * входящий вебхук сопоставляется с тенантом. Креды (токен бота, secret) хранятся
 * зашифрованными в credentials.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('type');
            $table->string('external_id')->nullable();
            $table->text('credentials');
            $table->boolean('is_active')->default(true);
            $table->jsonb('settings')->default('{}');
            $table->timestamps();

            $table->unique(['type', 'external_id']);

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
