<?php

declare(strict_types=1);

namespace App\Modules\Platform\Contracts;

use App\Modules\Platform\Models\SiteSetting;
use App\Modules\Platform\PlatformApiService;

/**
 * Публичный контракт модуля «Платформа» — единственная дверь для других модулей.
 * Снаружи доступно только это; SiteSettingsService/AnnouncementService/
 * DashboardCardService — приватная кухня модуля (её админ-UI живёт внутри Platform).
 * Реализация — {@see PlatformApiService}.
 */
interface PlatformApi
{
    /** Настройки публичного сайта (контакты, реквизиты, hero-блок) — для лендинга и кабинета. */
    public function current(): SiteSetting;
}
