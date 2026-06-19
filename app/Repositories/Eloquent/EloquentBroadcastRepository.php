<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\BroadcastStatus;
use App\Models\Broadcast;
use App\Models\BroadcastDelivery;
use App\Repositories\Contracts\BroadcastRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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

    public function recordDeliveries(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $now = now();

        $prepared = array_map(static fn (array $r): array => [
            'id' => (string) Str::uuid(),
            'tenant_id' => $r['tenant_id'],
            'broadcast_id' => $r['broadcast_id'],
            'client_id' => $r['client_id'] ?? null,
            'channel' => $r['channel'],
            'target' => $r['target'] ?? null,
            'status' => $r['status'],
            'error' => $r['error'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows);

        BroadcastDelivery::query()->insert($prepared);
    }

    public function deliveriesForCurrentTenant(string $broadcastId): Collection
    {
        return BroadcastDelivery::query()
            ->where('broadcast_id', $broadcastId)
            ->with('client')
            ->latest()
            ->get();
    }
}
