<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTO\NewTenantData;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Контракт доступа к данным тенантов. Единственный слой, работающий с БД
 * для сущности Tenant. Без бизнес-логики.
 */
interface TenantRepositoryInterface
{
    public function create(NewTenantData $data): Tenant;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Tenant $tenant, array $attributes): Tenant;

    public function find(string $id): ?Tenant;

    public function findBySlug(string $slug): ?Tenant;

    public function slugExists(string $slug): bool;

    /**
     * Все тенанты (реестр для супер-админа), новые сверху.
     *
     * @return Collection<int, Tenant>
     */
    public function all(): Collection;
}
