<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Contracts\IdentityApi;
use App\Modules\Identity\Repositories\Contracts\TenantRepositoryInterface;
use App\Modules\Identity\Repositories\Contracts\UserRepositoryInterface;
use App\Modules\Identity\Services\BusinessProvisioningService;
use App\Modules\Identity\Services\TenantService;
use App\Modules\Identity\Services\UserService;
use App\Shared\Enums\TenantPlan;
use App\Shared\Models\Tenant;
use App\Shared\Models\User;
use Illuminate\Support\Collection;

/**
 * Фасад модуля «Идентичность»: реализует {@see IdentityApi}, делегируя внутренним
 * сервисам и репозиториям. Имена методов совпадают с внутренними — потребители
 * меняют только тип в конструкторе.
 */
final class IdentityApiService implements IdentityApi
{
    public function __construct(
        private readonly TenantService $tenantService,
        private readonly UserService $userService,
        private readonly BusinessProvisioningService $provisioning,
        private readonly TenantRepositoryInterface $tenants,
        private readonly UserRepositoryInterface $users,
    ) {}

    public function createWithOwner(
        string $name,
        TenantPlan $plan,
        ?string $accessExpiresAt,
        string $ownerName,
        string $ownerEmail,
        string $ownerPassword,
    ): Tenant {
        return $this->provisioning->createWithOwner(
            $name,
            $plan,
            $accessExpiresAt,
            $ownerName,
            $ownerEmail,
            $ownerPassword,
        );
    }

    public function updateSubscription(Tenant $tenant, TenantPlan $plan, ?string $accessExpiresAt): Tenant
    {
        return $this->tenantService->updateSubscription($tenant, $plan, $accessExpiresAt);
    }

    public function setBusinessType(Tenant $tenant, ?string $businessType): Tenant
    {
        return $this->tenantService->setBusinessType($tenant, $businessType);
    }

    public function setOverrides(Tenant $tenant, array $overrides): Tenant
    {
        return $this->tenantService->setOverrides($tenant, $overrides);
    }

    public function block(Tenant $tenant): Tenant
    {
        return $this->tenantService->block($tenant);
    }

    public function unblock(Tenant $tenant): Tenant
    {
        return $this->tenantService->unblock($tenant);
    }

    public function updateBotMenu(Tenant $tenant, array $buttons): Tenant
    {
        return $this->tenantService->updateBotMenu($tenant, $buttons);
    }

    public function setWeeklyDigest(Tenant $tenant, bool $enabled): Tenant
    {
        return $this->tenantService->setWeeklyDigest($tenant, $enabled);
    }

    public function all(): Collection
    {
        return $this->tenants->all();
    }

    public function find(string $id): ?Tenant
    {
        return $this->tenants->find($id);
    }

    public function ownerOf(string $tenantId): ?User
    {
        return $this->userService->ownerOf($tenantId);
    }

    public function listForTenant(Tenant $tenant): Collection
    {
        return $this->userService->listForTenant($tenant);
    }

    public function setOwnerPassword(Tenant $tenant, string $password): bool
    {
        return $this->userService->setOwnerPassword($tenant, $password);
    }

    public function forCurrentTenant(): Collection
    {
        return $this->users->forCurrentTenant();
    }
}
