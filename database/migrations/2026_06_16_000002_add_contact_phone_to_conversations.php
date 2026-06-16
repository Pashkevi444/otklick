<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Телефон клиента для обратной связи — бот спрашивает его в диалоге, мы
 * сохраняем отдельно по диалогу (клиенту) и показываем в журнале.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('contact_phone')->nullable()->after('contact_name');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('contact_phone');
        });
    }
};
