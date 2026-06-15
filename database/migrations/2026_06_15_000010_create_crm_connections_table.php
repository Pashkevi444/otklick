<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Подключение тенанта к CRM (пока YClients). Креды (company_id, токен) хранятся
 * зашифрованными. Один тенант — одно подключение на провайдера.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_connections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('provider');
            $table->text('credentials');
            $table->boolean('is_active')->default(true);
            $table->jsonb('settings')->default('{}');
            $table->timestamps();

            $table->unique(['tenant_id', 'provider']);

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_connections');
    }
};
