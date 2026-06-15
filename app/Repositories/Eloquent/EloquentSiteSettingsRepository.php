<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\SiteSetting;
use App\Repositories\Contracts\SiteSettingsRepositoryInterface;

final class EloquentSiteSettingsRepository implements SiteSettingsRepositoryInterface
{
    /**
     * Дефолтный контент сайта (создаётся при первом обращении; дальше
     * редактируется супер-админом).
     *
     * @var array<string, string>
     */
    private const array DEFAULTS = [
        'hero_title' => 'AI-администратор, который не теряет ни одного клиента',
        'hero_subtitle' => 'Отвечает на сообщения в Telegram и WhatsApp за секунды, '.
            'консультирует по вашим услугам и записывает клиентов — круглосуточно.',
        'phone' => '89237032792',
        'email' => 'pasha.balaganskii@gmail.com',
        'telegram' => 'Pashkevi4',
        'access_note' => 'Оплаты пока нет. Чтобы получить доступ к админке — свяжитесь с нами.',
    ];

    public function current(): SiteSetting
    {
        return SiteSetting::query()->first() ?? SiteSetting::query()->create(self::DEFAULTS);
    }

    public function update(array $attributes): SiteSetting
    {
        $settings = $this->current();
        $settings->update($attributes);

        return $settings->refresh();
    }
}
