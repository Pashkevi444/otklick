<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Shared\Enums\TenantPlan;
use App\Shared\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Провижининг бизнеса: создание тенанта вместе с владельцем — атомарно.
 * Если создать владельца не удалось, тенант не должен остаться «сиротой»,
 * поэтому обе операции выполняются в одной транзакции (ACID).
 */
final readonly class BusinessProvisioningService
{
    public function __construct(
        private TenantService $tenants,
        private UserService $users,
    ) {}

    public function createWithOwner(
        string $name,
        TenantPlan $plan,
        ?string $accessExpiresAt,
        string $ownerName,
        string $ownerEmail,
        string $ownerPassword,
    ): Tenant {
        return DB::transaction(function () use ($name, $plan, $accessExpiresAt, $ownerName, $ownerEmail, $ownerPassword): Tenant {
            $tenant = $this->tenants->register($name, $plan, $accessExpiresAt);

            $this->users->createOwner($tenant, $ownerName, $ownerEmail, $ownerPassword);

            return $tenant;
        });
    }
}
