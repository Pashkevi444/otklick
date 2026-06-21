<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\CrmConnectionRepositoryInterface;
use App\Services\TenantService;
use App\Support\BotMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Главное меню бота: бизнес задаёт кнопки-подсказки, которые бот показывает после
 * приветствия. Пустое меню → бот не показывает ни кнопок, ни возврата. При
 * подключённой записи (YClients) кнопка «Записаться» добавляется ботом сама.
 */
final class BotMenuController extends Controller
{
    public function __construct(
        private readonly TenantService $tenants,
        private readonly CrmConnectionRepositoryInterface $crm,
    ) {}

    public function edit(Request $request): Response
    {
        $tenant = $request->user()->tenant;

        return Inertia::render('Cabinet/BotMenu', [
            'buttons' => BotMenu::items($tenant),
            'bookingButton' => BotMenu::BOOKING_BUTTON,
            // Подключён ли YClients — тогда «Записаться» добавляется автоматически.
            'bookingAutoAdded' => $this->crm->activeForCurrentTenant() !== null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'buttons' => ['array', 'max:12'],
            'buttons.*' => ['string', 'max:40'],
        ]);

        $this->tenants->updateBotMenu($request->user()->tenant, $data['buttons'] ?? []);

        return redirect()->route('cabinet.menu.edit')->with('success', 'Главное меню обновлено.');
    }
}
