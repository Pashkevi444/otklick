<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTO\NewTenantData;
use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentTenantRepository implements TenantRepositoryInterface
{
    public function create(NewTenantData $data): Tenant
    {
        return Tenant::create([
            'name' => $data->name,
            'slug' => $data->slug,
            'plan' => $data->plan,
            'settings' => $data->settings,
        ]);
    }

    public function update(Tenant $tenant, array $attributes): Tenant
    {
        $tenant->update($attributes);

        return $tenant->refresh();
    }

    public function find(string $id): ?Tenant
    {
        return Tenant::find($id);
    }

    public function findBySlug(string $slug): ?Tenant
    {
        return Tenant::where('slug', $slug)->first();
    }

    public function slugExists(string $slug): bool
    {
        return Tenant::where('slug', $slug)->exists();
    }

    public function all(): Collection
    {
        return Tenant::query()->latest()->get();
    }
}
