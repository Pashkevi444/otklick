<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CardState;
use App\Enums\DashboardCard;
use App\Http\Controllers\Controller;
use App\Services\DashboardCardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Редактор состояний плашек дашборда (супер-админ, глобально для всех бизнесов):
 * «новое» / «обновлено» / «тех. работы» (раздел становится недоступным к открытию).
 * Отдельно от прав/тарифов.
 */
final class DashboardCardController extends Controller
{
    public function __construct(private readonly DashboardCardService $cards) {}

    public function index(): Response
    {
        return Inertia::render('Admin/DashboardCards/Index', [
            'cards' => $this->cards->catalog(),
            'stateOptions' => array_map(
                static fn (CardState $s): array => ['value' => $s->value, 'label' => $s->label()],
                CardState::cases(),
            ),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'states' => ['required', 'array'],
            'states.*' => [Rule::in(CardState::values())],
        ]);

        /** @var array<string, string> $states */
        $states = $data['states'];
        foreach ($states as $key => $state) {
            if (DashboardCard::tryFrom($key) !== null) {
                $this->cards->set($key, CardState::from($state));
            }
        }

        return back()->with('success', 'Состояния плашек обновлены.');
    }
}
