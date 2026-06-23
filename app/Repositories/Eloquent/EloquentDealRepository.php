<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTO\DealData;
use App\Models\Deal;
use App\Repositories\Contracts\DealRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentDealRepository implements DealRepositoryInterface
{
    public function forCurrentTenant(): Collection
    {
        return Deal::query()
            ->with(['client', 'assignedUser'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function find(string $id): ?Deal
    {
        return Deal::query()->with(['client', 'stage', 'assignedUser'])->whereKey($id)->first();
    }

    public function create(DealData $data): Deal
    {
        return Deal::query()->create([
            'client_id' => $data->clientId,
            'stage_id' => $data->stageId,
            'title' => $data->title,
            'value' => $data->value,
            'assigned_user_id' => $data->assignedUserId,
            'source' => $data->source,
            'notes' => $data->notes,
            'custom' => $data->custom,
        ]);
    }

    public function update(Deal $deal, array $attributes): void
    {
        $deal->forceFill($attributes)->save();
    }

    public function delete(Deal $deal): void
    {
        $deal->delete();
    }
}
