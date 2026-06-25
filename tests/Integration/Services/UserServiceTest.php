<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Modules\Identity\Services\UserService;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_owner_persists_owner_bound_to_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $owner = $this->app->make(UserService::class)
            ->createOwner($tenant, 'Иван', 'ivan@biz.ru', 'secret-pass');

        $this->assertSame(UserRole::Owner, $owner->role);
        $this->assertSame($tenant->id, $owner->tenant_id);
        $this->assertDatabaseHas('users', [
            'email' => 'ivan@biz.ru',
            'role' => 'owner',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_list_for_tenant_returns_only_that_tenants_users(): void
    {
        $service = $this->app->make(UserService::class);

        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $service->createOwner($tenantA, 'A', 'a@biz.ru', 'secret-pass');
        $service->createOwner($tenantB, 'B', 'b@biz.ru', 'secret-pass');

        $this->assertCount(1, $service->listForTenant($tenantA));
        $this->assertSame('a@biz.ru', $service->listForTenant($tenantA)->first()->email);
    }
}
