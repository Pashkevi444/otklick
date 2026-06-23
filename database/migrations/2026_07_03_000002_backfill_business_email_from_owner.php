<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Бэкфилл почты бизнеса: у существующих тенантов без своей почты в профиле
 * подставляем почту владельца (по умолчанию). Дальше бизнес может изменить её в
 * профиле. Используется, в частности, в уведомлении забаненному клиенту.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('tenants')->get(['id', 'settings']) as $tenant) {
            $settings = json_decode((string) ($tenant->settings ?? '{}'), true);
            $settings = is_array($settings) ? $settings : [];
            $profile = is_array($settings['profile'] ?? null) ? $settings['profile'] : [];

            // Своя почта уже задана — не трогаем.
            if (isset($profile['email']) && trim((string) $profile['email']) !== '') {
                continue;
            }

            $ownerEmail = DB::table('users')
                ->where('tenant_id', $tenant->id)
                ->where('role', UserRole::Owner->value)
                ->value('email');

            if (! is_string($ownerEmail) || $ownerEmail === '') {
                continue;
            }

            $profile['email'] = $ownerEmail;
            $settings['profile'] = $profile;

            DB::table('tenants')->where('id', $tenant->id)->update([
                'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down(): void
    {
        // Бэкфилл данных — отката нет.
    }
};
