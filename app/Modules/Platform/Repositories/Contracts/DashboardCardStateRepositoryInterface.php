<?php

declare(strict_types=1);

namespace App\Modules\Platform\Repositories\Contracts;

use App\Shared\Enums\CardState;

interface DashboardCardStateRepositoryInterface
{
    /**
     * Карта «ключ плашки → состояние» (только заданные супер-админом).
     *
     * @return array<string, CardState>
     */
    public function map(): array;

    public function stateFor(string $cardKey): CardState;

    /** Задать состояние плашки (none — удаляет запись). */
    public function set(string $cardKey, CardState $state): void;
}
