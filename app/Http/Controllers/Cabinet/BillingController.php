<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Оплата подписки. Пока заглушка — онлайн-оплата появится позже, сейчас доступ
 * выдаёт оператор. Тариф и сроки — на странице «Подписка».
 */
final class BillingController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Cabinet/Billing');
    }
}
