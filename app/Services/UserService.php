<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\NewUserData;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Tenancy\TenantInitializer;
use Illuminate\Support\Collection;

/**
 * Бизнес-логика пользователей тенанта. Операции с тенант-данными выполняются
 * внутри тенант-контекста (RLS WITH CHECK + автоподстановка tenant_id).
 */
final readonly class UserService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private TenantInitializer $tenancy,
    ) {}

    public function createOwner(Tenant $tenant, string $name, string $email, string $password): User
    {
        return $this->tenancy->run($tenant->id, fn (): User => $this->users->create(new NewUserData(
            name: $name,
            email: $email,
            password: $password,
            role: UserRole::Owner,
            tenantId: $tenant->id,
        )));
    }

    /**
     * @return Collection<int, User>
     */
    public function listForTenant(Tenant $tenant): Collection
    {
        return $this->tenancy->run($tenant->id, fn (): Collection => $this->users->forCurrentTenant());
    }
}
