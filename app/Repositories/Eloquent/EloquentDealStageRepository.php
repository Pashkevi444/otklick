<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\DealStageAutomation;
use App\Models\DealStage;
use App\Repositories\Contracts\DealStageRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentDealStageRepository implements DealStageRepositoryInterface
{
    public function forCurrentTenant(): Collection
    {
        return DealStage::query()->orderBy('sort_order')->get();
    }

    public function existsForCurrentTenant(): bool
    {
        return DealStage::query()->exists();
    }

    public function firstByAutomation(DealStageAutomation $automation): ?DealStage
    {
        return DealStage::query()->where('automation', $automation->value)->orderBy('sort_order')->first();
    }

    public function create(array $attributes): DealStage
    {
        return DealStage::query()->create($attributes);
    }

    public function find(string $id): ?DealStage
    {
        return DealStage::query()->whereKey($id)->first();
    }
}
