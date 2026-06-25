<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Models\SiteSetting;
use App\Modules\Platform\Repositories\Contracts\SiteSettingsRepositoryInterface;

/**
 * Контент публичного сайта: чтение для лендинга и редактирование супер-админом.
 */
final readonly class SiteSettingsService
{
    public function __construct(
        private SiteSettingsRepositoryInterface $settings,
    ) {}

    public function current(): SiteSetting
    {
        return $this->settings->current();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes): SiteSetting
    {
        return $this->settings->update($attributes);
    }
}
