<?php

declare(strict_types=1);

namespace App\Modules\Platform;

use App\Modules\Platform\Repositories\Contracts\AnnouncementRepositoryInterface;
use App\Modules\Platform\Repositories\Contracts\DashboardCardStateRepositoryInterface;
use App\Modules\Platform\Repositories\Contracts\SiteSettingsRepositoryInterface;
use App\Modules\Platform\Repositories\Eloquent\EloquentAnnouncementRepository;
use App\Modules\Platform\Repositories\Eloquent\EloquentDashboardCardStateRepository;
use App\Modules\Platform\Repositories\Eloquent\EloquentSiteSettingsRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Модуль «Платформа» (кабинет супер-админа): новости/анонсы, состояния плашек
 * дашборда, настройки публичного сайта, редактирование шаблонов сценариев/БЗ/
 * промптов (модели шаблонов живут в своих доменных модулях — здесь только их
 * админ-UI), импесонация и управление тенантами. Контролирует площадку, не тенанта.
 */
final class PlatformServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        AnnouncementRepositoryInterface::class => EloquentAnnouncementRepository::class,
        DashboardCardStateRepositoryInterface::class => EloquentDashboardCardStateRepository::class,
        SiteSettingsRepositoryInterface::class => EloquentSiteSettingsRepository::class,
    ];
}
