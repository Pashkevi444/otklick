<?php

declare(strict_types=1);

namespace App\Modules\Platform;

use App\Modules\Platform\Contracts\PlatformApi;
use App\Modules\Platform\Models\SiteSetting;
use App\Modules\Platform\Services\SiteSettingsService;

/**
 * Фасад модуля «Платформа»: реализует {@see PlatformApi}, делегируя внутреннему
 * SiteSettingsService. Имена методов совпадают с внутренними — потребители
 * меняют только тип в конструкторе.
 */
final class PlatformApiService implements PlatformApi
{
    public function __construct(
        private readonly SiteSettingsService $site,
    ) {}

    public function current(): SiteSetting
    {
        return $this->site->current();
    }
}
