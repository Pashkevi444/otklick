<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTO\DealData;
use App\Models\Deal;
use Illuminate\Support\Collection;

interface DealRepositoryInterface
{
    /**
     * Сделки текущего тенанта (с клиентом и ответственным) для канбана/грида.
     *
     * @return Collection<int, Deal>
     */
    public function forCurrentTenant(): Collection;

    public function find(string $id): ?Deal;

    public function create(DealData $data): Deal;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Deal $deal, array $attributes): void;

    public function delete(Deal $deal): void;
}
