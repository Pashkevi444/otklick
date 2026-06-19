<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Repositories\Contracts\BroadcastRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class EloquentBroadcastRepository implements BroadcastRepositoryInterface
{
    public function find(string $id): ?Broadcast
    {
        return Broadcast::query()->whereKey($id)->first();
    }

    public function create(array $attributes): Broadcast
    {
        return Broadcast::create($attributes);
    }

    public function update(Broadcast $broadcast, array $attributes): void
    {
        $broadcast->forceFill($attributes)->save();
    }

    public function delete(Broadcast $broadcast): void
    {
        $broadcast->delete();
    }

    public function forCurrentTenant(): Collection
    {
        return Broadcast::query()->latest()->get();
    }

    public function dueForCurrentTenant(Carbon $now): Collection
    {
        return Broadcast::query()
            ->where('status', BroadcastStatus::Scheduled)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $now)
            ->get();
    }
}
