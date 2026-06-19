<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Пер-получательский журнал доставки рассылки: кому ушло, по какому каналу и с
 * какой ошибкой. Источник для отчёта по рассылке. Тенант-таблица (RLS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('broadcast_id')->index();
            $table->uuid('client_id')->nullable();
            $table->string('channel');           // telegram / vk / max / email
            $table->string('target')->nullable(); // chat id или email (для разбора)
            $table->string('status');            // sent / failed
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('broadcast_id')->references('id')->on('broadcasts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_deliveries');
    }
};
