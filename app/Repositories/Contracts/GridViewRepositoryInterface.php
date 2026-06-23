<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\GridEntity;
use App\Models\GridView;
use Illuminate\Support\Collection;

interface GridViewRepositoryInterface
{
    /**
     * Виды пользователя для сущности (в текущем тенанте), по имени.
     *
     * @return Collection<int, GridView>
     */
    public function forUserEntity(int $userId, GridEntity $entity): Collection;

    public function find(string $id): ?GridView;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): GridView;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(GridView $view, array $attributes): void;

    public function delete(GridView $view): void;
}
