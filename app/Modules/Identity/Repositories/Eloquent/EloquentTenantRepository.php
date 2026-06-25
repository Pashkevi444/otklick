<?php

declare(strict_types=1);

namespace App\Modules\Identity\Repositories\Eloquent;

use App\Modules\Identity\DTO\NewTenantData;
use App\Modules\Identity\Repositories\Contracts\TenantRepositoryInterface;
use App\Shared\Models\Tenant;
use App\Shared\Repositories\EloquentRepository;
use Illuminate\Support\Collection;

/**
 * @extends EloquentRepository<Tenant>
 */
final class EloquentTenantRepository extends EloquentRepository implements TenantRepositoryInterface
{
    protected function model(): string
    {
        return Tenant::class;
    }

    public function create(NewTenantData $data): Tenant
    {
        return Tenant::create([
            'name' => $data->name,
            'slug' => $data->slug,
            'plan' => $data->plan,
            'access_expires_at' => $data->accessExpiresAt,
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
        return $this->findById($id);
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
