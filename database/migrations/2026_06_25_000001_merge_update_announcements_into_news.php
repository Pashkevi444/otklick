<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «Обновления» как отдельную ленту убрали — оставляем только «Новости». Переносим
 * существующие анонсы типа `update` в `news`, чтобы они не осиротели и каст типа
 * не падал после удаления кейса AnnouncementType::Update.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('announcements')->where('type', 'update')->update(['type' => 'news']);
    }

    public function down(): void
    {
        // Необратимо: какие из новостей были «обновлениями» — не сохраняем.
    }
};
