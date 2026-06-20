<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CardState;
use App\Enums\DashboardCard;
use App\Repositories\Contracts\DashboardCardStateRepositoryInterface;

/**
 * Состояния плашек дашборда, заданные супер-админом глобально (для всех бизнесов):
 * «новое» / «обновлено» / «тех. работы». Отдельно от прав/тарифов.
 */
final readonly class DashboardCardService
{
    public function __construct(private DashboardCardStateRepositoryInterface $states) {}

    /**
     * Карта «ключ плашки → состояние» (строки) для фронта дашборда. Только
     * не-обычные состояния (обычные не храним).
     *
     * @return array<string, string>
     */
    public function statesForFrontend(): array
    {
        $map = [];
        foreach ($this->states->map() as $key => $state) {
            $map[$key] = $state->value;
        }

        return $map;
    }

    /**
     * Каталог всех плашек с текущим состоянием — для редактора СУ.
     *
     * @return list<array{key: string, label: string, state: string}>
     */
    public function catalog(): array
    {
        $map = $this->states->map();

        return array_map(static fn (DashboardCard $card): array => [
            'key' => $card->value,
            'label' => $card->label(),
            'state' => ($map[$card->value] ?? CardState::None)->value,
        ], DashboardCard::cases());
    }

    public function set(string $cardKey, CardState $state): void
    {
        // Игнорируем неизвестные ключи (защита от мусора в запросе).
        if (DashboardCard::tryFrom($cardKey) === null) {
            return;
        }

        $this->states->set($cardKey, $state);
    }

    public function stateFor(string $cardKey): CardState
    {
        return $this->states->stateFor($cardKey);
    }
}
