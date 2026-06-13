<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\DTO\NewTenantData;
use App\Enums\TenantPlan;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TenantRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private TenantRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->app->make(TenantRepositoryInterface::class);
    }

    public function test_create_persists_tenant_with_casts(): void
    {
        $tenant = $this->repository->create(new NewTenantData(
            name: 'Стоматология «Улыбка»',
            slug: 'ulybka',
            plan: TenantPlan::Pro,
            settings: ['timezone' => 'Europe/Moscow'],
        ));

        $this->assertDatabaseHas('tenants', ['slug' => 'ulybka', 'plan' => 'pro']);
        $this->assertSame(TenantPlan::Pro, $tenant->fresh()->plan);
        $this->assertSame('Europe/Moscow', $tenant->fresh()->settings['timezone']);
    }

    public function test_find_by_slug_and_slug_exists(): void
    {
        $this->repository->create(new NewTenantData('Barber', 'barber', TenantPlan::Trial));

        $this->assertNotNull($this->repository->findBySlug('barber'));
        $this->assertNull($this->repository->findBySlug('missing'));
        $this->assertTrue($this->repository->slugExists('barber'));
        $this->assertFalse($this->repository->slugExists('missing'));
    }
}
