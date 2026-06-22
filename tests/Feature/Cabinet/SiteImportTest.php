<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Jobs\ImportKnowledgeFromSite;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SiteImportStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

final class SiteImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantWithOwner(): array
    {
        $tenant = Tenant::factory()->create();

        return [$tenant, User::factory()->owner($tenant)->create()];
    }

    public function test_owner_starts_import_and_job_is_dispatched(): void
    {
        Bus::fake();
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)
            ->post('/cabinet/knowledge/import-site', ['url' => 'https://mysite.ru'])
            ->assertRedirect(route('cabinet.knowledge.index'));

        Bus::assertDispatched(
            ImportKnowledgeFromSite::class,
            fn (ImportKnowledgeFromSite $job): bool => $job->tenantId === $tenant->id && $job->url === 'https://mysite.ru',
        );
    }

    public function test_bare_domain_is_accepted(): void
    {
        Bus::fake();
        [, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)
            ->post('/cabinet/knowledge/import-site', ['url' => 'mysite.ru'])
            ->assertRedirect(route('cabinet.knowledge.index'));

        Bus::assertDispatched(
            ImportKnowledgeFromSite::class,
            fn (ImportKnowledgeFromSite $job): bool => $job->url === 'https://mysite.ru',
        );
    }

    public function test_invalid_url_is_rejected(): void
    {
        Bus::fake();
        [, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)
            ->post('/cabinet/knowledge/import-site', ['url' => 'не ссылка'])
            ->assertSessionHasErrors('url');

        Bus::assertNotDispatched(ImportKnowledgeFromSite::class);
    }

    public function test_status_endpoint_returns_progress(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $this->app->make(SiteImportStatus::class)->report($tenant->id, 42, 3);

        $this->actingAs($owner)
            ->getJson('/cabinet/knowledge/import-site/status')
            ->assertOk()
            ->assertJson(['percent' => 42, 'state' => 'running', 'created' => 3]);
    }

    public function test_import_is_not_available_to_guests(): void
    {
        $this->post('/cabinet/knowledge/import-site', ['url' => 'https://mysite.ru'])->assertRedirect('/login');
        $this->get('/cabinet/knowledge/import-site/status')->assertRedirect('/login');
    }
}
