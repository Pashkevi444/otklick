<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Modules\Identity\DTO\NewTenantData;
use App\Modules\Identity\Events\TenantRegistered;
use App\Modules\Identity\Repositories\Contracts\TenantRepositoryInterface;
use App\Modules\Identity\Services\TenantService;
use App\Shared\Enums\TenantPlan;
use App\Shared\Models\Tenant;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

final class TenantServiceTest extends TestCase
{
    public function test_register_creates_tenant_with_slug_and_default_plan(): void
    {
        Event::fake();

        $repo = Mockery::mock(TenantRepositoryInterface::class);
        $repo->shouldReceive('slugExists')->with('beauty-bar')->andReturnFalse();
        $repo->shouldReceive('create')
            ->once()
            ->withArgs(function (NewTenantData $data): bool {
                return $data->name === 'Beauty Bar'
                    && $data->slug === 'beauty-bar'
                    && $data->plan === TenantPlan::Trial;
            })
            ->andReturn(new Tenant(['name' => 'Beauty Bar', 'slug' => 'beauty-bar']));

        $service = new TenantService($repo);
        $tenant = $service->register('Beauty Bar');

        $this->assertSame('beauty-bar', $tenant->slug);
        Event::assertDispatched(TenantRegistered::class);
    }

    public function test_register_makes_slug_unique_when_taken(): void
    {
        Event::fake();

        $repo = Mockery::mock(TenantRepositoryInterface::class);
        $repo->shouldReceive('slugExists')->with('barber')->andReturnTrue();
        $repo->shouldReceive('slugExists')->with('barber-2')->andReturnFalse();
        $repo->shouldReceive('create')
            ->once()
            ->withArgs(fn (NewTenantData $data): bool => $data->slug === 'barber-2')
            ->andReturn(new Tenant(['slug' => 'barber-2']));

        $service = new TenantService($repo);
        $tenant = $service->register('Barber');

        $this->assertSame('barber-2', $tenant->slug);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
