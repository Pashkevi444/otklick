<?php

declare(strict_types=1);

namespace App\Modules\Platform\Repositories\Contracts;

use App\Modules\Platform\Models\SiteSetting;

/**
 * Доступ к настройкам сайта (singleton). Возвращает существующую строку или
 * создаёт её с дефолтным контентом.
 */
interface SiteSettingsRepositoryInterface
{
    public function current(): SiteSetting;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes): SiteSetting;
}
