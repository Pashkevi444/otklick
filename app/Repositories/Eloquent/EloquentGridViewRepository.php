<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\GridEntity;
use App\Models\GridView;
use App\Repositories\Contracts\GridViewRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentGridViewRepository implements GridViewRepositoryInterface
{
    public function forUserEntity(int $userId, GridEntity $entity): Collection
    {
        return GridView::query()
            ->where('user_id', $userId)
            ->where('entity', $entity->value)
            ->orderBy('name')
            ->get();
    }

    public function find(string $id): ?GridView
    {
        return GridView::query()->whereKey($id)->first();
    }

    public function create(array $attributes): GridView
    {
        return GridView::query()->create($attributes);
    }

    public function update(GridView $view, array $attributes): void
    {
        $view->forceFill($attributes)->save();
    }

    public function delete(GridView $view): void
    {
        $view->delete();
    }
}
