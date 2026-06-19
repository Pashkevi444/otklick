<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Broadcast;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Доступ к рассылкам тенанта. Скоупится текущим тенантом (RLS + scope).
 */
interface BroadcastRepositoryInterface
{
    public function find(string $id): ?Broadcast;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Broadcast;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Broadcast $broadcast, array $attributes): void;

    public function delete(Broadcast $broadcast): void;

    /**
     * @return Collection<int, Broadcast>
     */
    public function forCurrentTenant(): Collection;

    /**
     * «Созревшие» запланированные рассылки текущего тенанта (next_run_at ≤ $now).
     * Планировщик зовёт это в контексте каждого тенанта.
     *
     * @return Collection<int, Broadcast>
     */
    public function dueForCurrentTenant(Carbon $now): Collection;
}
