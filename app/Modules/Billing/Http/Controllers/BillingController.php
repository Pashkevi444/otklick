<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Services\SiteSettingsService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Оплата подписки. Пока заглушка — онлайн-оплата появится позже, сейчас доступ
 * выдаёт оператор. Контакты для связи берём из настроек сайта (единый источник —
 * супер-админка), а не хардкодом.
 */
final class BillingController extends Controller
{
    public function __invoke(SiteSettingsService $site): Response
    {
        $settings = $site->current();

        return Inertia::render('Cabinet/Billing', [
            'support' => [
                'email' => $settings->email,
                'phone' => $settings->phone,
                'telegram' => $settings->telegram,
            ],
        ]);
    }
}
