<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Одноразовые коды подтверждения смены e-mail. Код уходит на НОВЫЙ адрес; пока
 * не подтверждён — почта не меняется. Храним только хеш кода. Одна заявка на
 * пользователя (перезаписывается).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_change_codes', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->primary();
            $table->string('new_email');
            $table->string('code_hash');
            $table->timestamp('created_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_change_codes');
    }
};
