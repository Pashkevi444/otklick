<?php

declare(strict_types=1);

namespace Tests\Integration\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Главный инвариант продукта: один тенант НИКОГДА не видит данные другого.
 */
final class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_record_assigns_current_tenant_automatically(): void
    {
        $tenantA = Tenant::factory()->create();
        $context = $this->app->make(TenantContext::class);
        $context->set($tenantA->id);

        $user = User::factory()->create();

        $this->assertSame($tenantA->id, $user->tenant_id);
    }

    public function test_global_scope_hides_other_tenants_data(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $context = $this->app->make(TenantContext::class);

        $context->set($tenantA->id);
        User::factory()->count(2)->create();

        $context->set($tenantB->id);
        User::factory()->count(3)->create();

        // В контексте B видны только пользователи B.
        $this->assertSame(3, User::query()->count());

        // Переключаемся на A — видны только пользователи A.
        $context->set($tenantA->id);
        $this->assertSame(2, User::query()->count());
    }

    public function test_without_tenant_context_scope_is_not_applied(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $context = $this->app->make(TenantContext::class);

        $context->set($tenantA->id);
        User::factory()->create();
        $context->set($tenantB->id);
        User::factory()->create();

        $context->forget();

        // Без тенанта в контексте (консоль/бутстрап) — глобальный scope не фильтрует.
        $this->assertSame(2, User::query()->count());
    }
}
