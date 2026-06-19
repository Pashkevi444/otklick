<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Flow;
use Illuminate\Support\Collection;

/**
 * Доступ к сценариям-воронкам тенанта. Скоупится текущим тенантом (RLS + scope).
 */
interface FlowRepositoryInterface
{
    public function find(string $id): ?Flow;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Flow;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Flow $flow, array $attributes): void;

    public function delete(Flow $flow): void;

    /**
     * @return Collection<int, Flow>
     */
    public function forCurrentTenant(): Collection;

    /**
     * Активные воронки текущего тенанта (для запуска по триггеру).
     *
     * @return Collection<int, Flow>
     */
    public function activeForCurrentTenant(): Collection;
}
