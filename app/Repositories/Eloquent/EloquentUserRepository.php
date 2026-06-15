<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTO\NewUserData;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * @extends EloquentRepository<User>
 */
final class EloquentUserRepository extends EloquentRepository implements UserRepositoryInterface
{
    protected function model(): string
    {
        return User::class;
    }

    public function create(NewUserData $data): User
    {
        return User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => $data->password, // каст 'hashed' хеширует при записи
            'role' => $data->role,
            'tenant_id' => $data->tenantId,
        ]);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function forCurrentTenant(): Collection
    {
        return User::query()->latest()->get();
    }
}
