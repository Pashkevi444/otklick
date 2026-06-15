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
