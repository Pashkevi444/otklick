<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTO\NewUserData;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Контракт доступа к данным пользователей.
 */
interface UserRepositoryInterface
{
    public function create(NewUserData $data): User;

    public function findByEmail(string $email): ?User;

    public function findForCurrentTenant(string $id): ?User;

    public function countForCurrentTenant(): int;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateUser(User $user, array $attributes): User;

    public function deleteUser(User $user): void;

    /**
     * Владелец тенанта в текущем тенант-контексте (scoped/RLS).
     */
    public function ownerForCurrentTenant(): ?User;

    /**
     * Пользователи текущего тенант-контекста (scoped/RLS).
     *
     * @return Collection<int, User>
     */
    public function forCurrentTenant(): Collection;
}
