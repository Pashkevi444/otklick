<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Связь лида (диалога) с карточкой клиента: client_id заполняется, когда у
 * диалога появился телефон (см. ContactCapture → ClientService). Имя/телефон
 * клиента тянутся из карточки.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->uuid('client_id')->nullable()->index()->after('crm_connection_id');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('client_id');
        });
    }
};
