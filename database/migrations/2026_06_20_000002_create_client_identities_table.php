<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Нативные идентичности клиента по каналам: то, что канал отдаёт без спроса
 * (Telegram chat_id, WhatsApp phone@c.us, VK peer id, MAX user id). По ним бот
 * узнаёт вернувшегося в конкретном канале, не переспрашивая контакты. Дедуп
 * клиента — в каждом канале свой, по `(tenant_id, channel_type, identity)`.
 * При удалении клиента идентичности уходят каскадом → бот «забывает» его.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_identities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('client_id');
            $table->string('channel_type');
            $table->string('identity'); // нативный id чата/пользователя в канале
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
            // Один клиент на нативный id канала в пределах тенанта.
            $table->unique(['tenant_id', 'channel_type', 'identity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_identities');
    }
};
