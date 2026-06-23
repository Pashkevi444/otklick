<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\DealStage;
use Illuminate\Support\Collection;

interface DealStageRepositoryInterface
{
    /**
     * Стадии воронки текущего тенанта по порядку.
     *
     * @return Collection<int, DealStage>
     */
    public function forCurrentTenant(): Collection;

    public function existsForCurrentTenant(): bool;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): DealStage;

    public function find(string $id): ?DealStage;
}
