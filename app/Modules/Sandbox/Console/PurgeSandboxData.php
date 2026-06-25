<?php

declare(strict_types=1);

namespace App\Modules\Sandbox\Console;

use App\Modules\Sandbox\Models\SandboxRecord;
use App\Modules\Sandbox\Repositories\Contracts\SandboxRepositoryInterface;
use App\Shared\Models\Tenant;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Console\Command;

/**
 * Чистит данные «песочницы» тестирования бота (тестовые диалоги/клиенты/каналы и
 * реестр {@see SandboxRecord}) по всем тенантам в их контексте (RLS).
 * Запускается планировщиком раз в сутки (см. routes/console.php), чтобы тестовые
 * прогоны не накапливались в БД.
 */
final class PurgeSandboxData extends Command
{
    protected $signature = 'sandbox:purge';

    protected $description = 'Удаляет тестовые данные песочницы (тестирование бота) по всем тенантам.';

    public function handle(TenantInitializer $tenancy, SandboxRepositoryInterface $sandbox): int
    {
        $removed = 0;

        Tenant::query()->pluck('id')->each(function (string $tenantId) use ($tenancy, $sandbox, &$removed): void {
            $removed += $tenancy->run($tenantId, fn (): int => $sandbox->purgeForCurrentTenant());
        });

        $this->info("Удалено тестовых записей песочницы: {$removed}.");

        return self::SUCCESS;
    }
}
