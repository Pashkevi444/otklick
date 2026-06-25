<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Console\CreateSuperAdmin;
use App\Modules\Identity\Contracts\IdentityApi;
use App\Modules\Identity\Repositories\Contracts\EmailChangeCodeRepositoryInterface;
use App\Modules\Identity\Repositories\Contracts\PasswordResetCodeRepositoryInterface;
use App\Modules\Identity\Repositories\Contracts\TenantRepositoryInterface;
use App\Modules\Identity\Repositories\Contracts\UserRepositoryInterface;
use App\Modules\Identity\Repositories\Eloquent\EloquentEmailChangeCodeRepository;
use App\Modules\Identity\Repositories\Eloquent\EloquentPasswordResetCodeRepository;
use App\Modules\Identity\Repositories\Eloquent\EloquentTenantRepository;
use App\Modules\Identity\Repositories\Eloquent\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Модуль «Идентичность»: вход/2FA, аккаунт (смена email/пароля), команда тенанта,
 * провижининг тенанта/владельца, профиль бизнеса. Сами сущности User и Tenant
 * живут в общем ядре (App\Models) — это ось мультитенантности и аутентификации,
 * на которую ссылаются все модули; модуль владеет лишь их репозиториями и логикой.
 */
final class IdentityServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        IdentityApi::class => IdentityApiService::class,
        UserRepositoryInterface::class => EloquentUserRepository::class,
        TenantRepositoryInterface::class => EloquentTenantRepository::class,
        EmailChangeCodeRepositoryInterface::class => EloquentEmailChangeCodeRepository::class,
        PasswordResetCodeRepositoryInterface::class => EloquentPasswordResetCodeRepository::class,
    ];

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CreateSuperAdmin::class]);
        }
    }
}
