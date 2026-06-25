<?php

declare(strict_types=1);

namespace App\Modules\Flows\Repositories\Eloquent;

use App\Modules\Flows\Models\Flow;
use App\Modules\Flows\Repositories\Contracts\FlowRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentFlowRepository implements FlowRepositoryInterface
{
    public function find(string $id): ?Flow
    {
        return Flow::query()->whereKey($id)->first();
    }

    public function create(array $attributes): Flow
    {
        return Flow::create($attributes);
    }

    public function update(Flow $flow, array $attributes): void
    {
        $flow->forceFill($attributes)->save();
    }

    public function delete(Flow $flow): void
    {
        $flow->delete();
    }

    public function forCurrentTenant(): Collection
    {
        return Flow::query()->latest()->get();
    }

    public function activeForCurrentTenant(): Collection
    {
        return Flow::query()->where('is_active', true)->get();
    }
}
