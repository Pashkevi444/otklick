<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\DealStageAutomation;
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

    /** Первая стадия с заданной automation-ролью (для авто-движения сделки). */
    public function firstByAutomation(DealStageAutomation $automation): ?DealStage;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): DealStage;

    public function find(string $id): ?DealStage;
}
