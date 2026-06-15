<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\DTO\KnowledgeEntryData;
use App\Models\Tenant;
use App\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class KnowledgeEntryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private KnowledgeEntryRepositoryInterface $repository;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->app->make(KnowledgeEntryRepositoryInterface::class);
        $this->tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($this->tenant->id);
    }

    public function test_crud_within_tenant(): void
    {
        $entry = $this->repository->create(new KnowledgeEntryData('Доставка', 'Бесплатно от 1000₽', true));

        $this->assertSame($this->tenant->id, $entry->tenant_id);
        $this->assertCount(1, $this->repository->forCurrentTenant());

        $updated = $this->repository->update($entry, new KnowledgeEntryData('Доставка', 'Платно', false));
        $this->assertSame('Платно', $updated->content);
        $this->assertFalse($updated->is_published);

        $this->repository->delete($updated);
        $this->assertCount(0, $this->repository->forCurrentTenant());
    }

    public function test_entries_are_isolated_between_tenants(): void
    {
        $this->repository->create(new KnowledgeEntryData('A', 'A', true));

        $other = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($other->id);

        $this->assertCount(0, $this->repository->forCurrentTenant());
    }
}
