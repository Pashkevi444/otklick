<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\NewUserData;
use App\Enums\CabinetSection;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Tenancy\TenantInitializer;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

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
     * Установить пароль владельцу бизнеса (действие супер-админа).
     * Возвращает false, если у тенанта нет владельца.
     */
    public function setOwnerPassword(Tenant $tenant, string $password): bool
    {
        return $this->tenancy->run($tenant->id, function () use ($password): bool {
            $owner = $this->users->ownerForCurrentTenant();

            if ($owner === null) {
                return false;
            }

            $owner->update(['password' => $password]); // каст 'hashed' хеширует

            return true;
        });
    }

    /**
     * @return Collection<int, User>
     */
    public function listForTenant(Tenant $tenant): Collection
    {
        return $this->tenancy->run($tenant->id, fn (): Collection => $this->users->forCurrentTenant());
    }

    /**
     * Владелец бизнеса по id тенанта (для входа супер-админа в кабинет бизнеса).
     */
    public function ownerOf(string $tenantId): ?User
    {
        return $this->tenancy->run($tenantId, fn (): ?User => $this->users->ownerForCurrentTenant());
    }

    /**
     * Добавляет сотрудника (оператора) с ограниченным набором разделов.
     * Учитывает лимит пользователей тарифа (maxOperators) — считаются все
     * пользователи бизнеса, включая владельца.
     *
     * @param  list<string>  $permissions
     *
     * @throws ValidationException если достигнут лимит тарифа
     */
    public function addMember(Tenant $tenant, string $name, string $email, string $password, array $permissions): User
    {
        return $this->tenancy->run($tenant->id, function () use ($tenant, $name, $email, $password, $permissions): User {
            if ($this->users->countForCurrentTenant() >= $tenant->features()->maxOperators) {
                throw ValidationException::withMessages([
                    'email' => "Достигнут лимит пользователей кабинета по тарифу ({$tenant->features()->maxOperators}).",
                ]);
            }

            return $this->users->create(new NewUserData(
                name: $name,
                email: $email,
                password: $password,
                role: UserRole::Member,
                tenantId: $tenant->id,
                permissions: $this->sanitize($permissions),
            ));
        });
    }

    /**
     * Обновляет права (и имя) сотрудника. Владельца не трогает. null — не найден.
     *
     * @param  list<string>  $permissions
     */
    public function updateMember(Tenant $tenant, string $userId, ?string $name, array $permissions): ?User
    {
        return $this->tenancy->run($tenant->id, function () use ($userId, $name, $permissions): ?User {
            $user = $this->users->findForCurrentTenant($userId);

            if ($user === null || $user->role !== UserRole::Member) {
                return null;
            }

            $attributes = ['permissions' => $this->sanitize($permissions)];
            if ($name !== null && $name !== '') {
                $attributes['name'] = $name;
            }

            return $this->users->updateUser($user, $attributes);
        });
    }

    /**
     * Удаляет сотрудника (только роль Member). false — не найден/не сотрудник.
     */
    public function removeMember(Tenant $tenant, string $userId): bool
    {
        return $this->tenancy->run($tenant->id, function () use ($userId): bool {
            $user = $this->users->findForCurrentTenant($userId);

            if ($user === null || $user->role !== UserRole::Member) {
                return false;
            }

            $this->users->deleteUser($user);

            return true;
        });
    }

    /**
     * Оставляет только валидные ключи разделов.
     *
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function sanitize(array $permissions): array
    {
        return array_values(array_intersect(CabinetSection::values(), $permissions));
    }
}
