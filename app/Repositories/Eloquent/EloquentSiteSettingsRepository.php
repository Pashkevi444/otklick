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
        'hero_title' => 'Цифровой администратор, который отвечает клиентам вместо вас',
        'hero_subtitle' => 'Отклик мгновенно отвечает в Telegram и на сайте по базе знаний '.
            'вашего бизнеса, записывает клиентов и передаёт горячих менеджеру — круглосуточно, без выходных.',
        'phone' => '89237032792',
        'email' => 'pasha.balaganskii@gmail.com',
        'telegram' => 'Pashkevi4',
        'legal_name' => 'ИП Балаганский Павел Сергеевич',
        'inn' => '543807917255',
        'ogrnip' => '323547600043330',
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
