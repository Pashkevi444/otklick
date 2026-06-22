<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Откат нишевых SEO-лендингов: таблица `site_niche_pages` больше не нужна
 * (решили не плодить 26 отдельных лендингов — сайт делаем многостраничным, но
 * без страниц-под-нишу). Безопасно на проде (где таблица была) и на свежей БД.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('site_niche_pages');
    }

    public function down(): void
    {
        // Восстанавливать не нужно — функциональность отменена.
    }
};
