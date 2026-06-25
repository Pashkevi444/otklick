<?php

declare(strict_types=1);

namespace App\Modules\Sandbox;

use App\Modules\Sandbox\Console\PurgeSandboxData;
use App\Modules\Sandbox\Repositories\Contracts\SandboxRepositoryInterface;
use App\Modules\Sandbox\Repositories\Eloquent\EloquentSandboxRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Модуль «Тестирование бота» (песочница): прогон диалога с ботом без реальной
 * доставки и без следа в проде. Инфраструктура изоляции (TestContext, SandboxScope,
 * трейт MarksSandbox) — в общем ядре (App\Tenancy / App\Models\Concerns), ею
 * пользуются модели всех модулей; здесь — реестр sandbox-записей и его очистка.
 */
final class SandboxServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        SandboxRepositoryInterface::class => EloquentSandboxRepository::class,
    ];

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([PurgeSandboxData::class]);
        }
    }
}
