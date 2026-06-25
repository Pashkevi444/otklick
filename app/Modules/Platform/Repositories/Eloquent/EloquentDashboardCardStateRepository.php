<?php

declare(strict_types=1);

namespace App\Modules\Platform\Repositories\Eloquent;

use App\Modules\Platform\Models\DashboardCardState;
use App\Modules\Platform\Repositories\Contracts\DashboardCardStateRepositoryInterface;
use App\Shared\Enums\CardState;

final class EloquentDashboardCardStateRepository implements DashboardCardStateRepositoryInterface
{
    public function map(): array
    {
        return DashboardCardState::query()
            ->get()
            ->mapWithKeys(static fn (DashboardCardState $c): array => [$c->card_key => $c->state])
            ->all();
    }

    public function stateFor(string $cardKey): CardState
    {
        return $this->map()[$cardKey] ?? CardState::None;
    }

    public function set(string $cardKey, CardState $state): void
    {
        if ($state === CardState::None) {
            DashboardCardState::query()->where('card_key', $cardKey)->delete();

            return;
        }

        DashboardCardState::query()->updateOrCreate(
            ['card_key' => $cardKey],
            ['state' => $state],
        );
    }
}
