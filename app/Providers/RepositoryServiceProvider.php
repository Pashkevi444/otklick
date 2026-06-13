<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Repositories\Eloquent\EloquentTenantRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Привязка контрактов репозиториев к Eloquent-реализациям.
 * Сервисы зависят от интерфейсов (DIP), а не от конкретных классов.
 */
final class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        TenantRepositoryInterface::class => EloquentTenantRepository::class,
    ];
}
