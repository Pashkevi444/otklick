<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTO\NewCrmConnectionData;
use App\Enums\CrmProvider;
use App\Models\CrmConnection;
use App\Repositories\Contracts\CrmConnectionRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * @extends EloquentRepository<CrmConnection>
 */
final class EloquentCrmConnectionRepository extends EloquentRepository implements CrmConnectionRepositoryInterface
{
    protected function model(): string
    {
        return CrmConnection::class;
    }

    public function create(NewCrmConnectionData $data): CrmConnection
    {
        return CrmConnection::create([
            'tenant_id' => $data->tenantId,
            'provider' => $data->provider,
            'credentials' => $data->credentials,
            'is_active' => true,
            'settings' => $data->settings,
        ]);
    }

    public function find(string $id): ?CrmConnection
    {
        return $this->findById($id);
    }

    public function findByProviderForCurrentTenant(CrmProvider $provider): ?CrmConnection
    {
        return CrmConnection::query()->where('provider', $provider)->first();
    }

    public function activeForCurrentTenant(): ?CrmConnection
    {
        return CrmConnection::query()->where('is_active', true)->latest()->first();
    }

    public function forCurrentTenant(): Collection
    {
        return CrmConnection::query()->latest()->get();
    }

    public function updateSettings(CrmConnection $connection, array $settings): void
    {
        $connection->forceFill(['settings' => $settings])->save();
    }

    public function delete(CrmConnection $connection): void
    {
        $this->remove($connection);
    }
}
