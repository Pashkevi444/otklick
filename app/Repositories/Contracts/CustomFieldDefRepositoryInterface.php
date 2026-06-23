<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\CustomFieldEntity;
use App\Models\CustomFieldDef;
use Illuminate\Support\Collection;

interface CustomFieldDefRepositoryInterface
{
    /**
     * Определения полей сущности текущего тенанта, по порядку.
     *
     * @return Collection<int, CustomFieldDef>
     */
    public function forEntity(CustomFieldEntity $entity): Collection;

    /**
     * Все определения полей текущего тенанта (обе сущности), по порядку.
     *
     * @return Collection<int, CustomFieldDef>
     */
    public function forCurrentTenant(): Collection;

    public function find(string $id): ?CustomFieldDef;

    public function existsKey(CustomFieldEntity $entity, string $key): bool;

    public function nextSortOrder(CustomFieldEntity $entity): int;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CustomFieldDef;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CustomFieldDef $def, array $attributes): void;

    public function delete(CustomFieldDef $def): void;
}
