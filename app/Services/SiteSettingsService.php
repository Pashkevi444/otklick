<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SiteSetting;
use App\Repositories\Contracts\SiteSettingsRepositoryInterface;

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
