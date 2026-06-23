<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\CustomFieldEntity;
use App\Models\CustomFieldDef;
use App\Repositories\Contracts\CustomFieldDefRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentCustomFieldDefRepository implements CustomFieldDefRepositoryInterface
{
    public function forEntity(CustomFieldEntity $entity): Collection
    {
        return CustomFieldDef::query()
            ->where('entity', $entity->value)
            ->orderBy('sort_order')
            ->get();
    }

    public function forCurrentTenant(): Collection
    {
        return CustomFieldDef::query()->orderBy('entity')->orderBy('sort_order')->get();
    }

    public function find(string $id): ?CustomFieldDef
    {
        return CustomFieldDef::query()->whereKey($id)->first();
    }

    public function existsKey(CustomFieldEntity $entity, string $key): bool
    {
        return CustomFieldDef::query()
            ->where('entity', $entity->value)
            ->where('key', $key)
            ->exists();
    }

    public function nextSortOrder(CustomFieldEntity $entity): int
    {
        return (int) CustomFieldDef::query()->where('entity', $entity->value)->max('sort_order') + 1;
    }

    public function create(array $attributes): CustomFieldDef
    {
        return CustomFieldDef::query()->create($attributes);
    }

    public function update(CustomFieldDef $def, array $attributes): void
    {
        $def->forceFill($attributes)->save();
    }

    public function delete(CustomFieldDef $def): void
    {
        $def->delete();
    }
}
