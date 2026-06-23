<?php

declare(strict_types=1);

use App\Services\LeadBackfillService;
use Illuminate\Database\Migrations\Migration;

/**
 * Бэкфилл лидов из диалогов: у каждого существующего тенанта диалоги, к которым
 * привязан клиент, превращаются в лид (входящее, source=bot, status=new) — чтобы
 * во встроенной CRM появились существующие обращения. Логика — в
 * {@see LeadBackfillService} (идемпотентно, RLS-безопасно, тестируется).
 */
return new class extends Migration
{
    public function up(): void
    {
        app(LeadBackfillService::class)->run();
    }

    public function down(): void
    {
        // Бэкфилл данных — обратной операции нет.
    }
};
