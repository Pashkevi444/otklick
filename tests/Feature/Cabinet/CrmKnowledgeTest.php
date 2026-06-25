<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Modules\Booking\Models\CrmConnection;
use App\Modules\Knowledge\Jobs\SyncCrmKnowledge;
use App\Shared\Models\Tenant;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class CrmKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_dispatches_background_job(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->max()->create();
        CrmConnection::factory()->create(['tenant_id' => $tenant->id]);
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->post('/cabinet/knowledge-crm/sync')
            ->assertRedirect(route('cabinet.knowledge.crm'));

        Queue::assertPushed(SyncCrmKnowledge::class, fn (SyncCrmKnowledge $job): bool => $job->tenantId === $tenant->id);
    }

    public function test_sync_rejected_without_connection(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->post('/cabinet/knowledge-crm/sync')->assertStatus(422);
    }

    public function test_status_endpoint_reports_progress(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->max()->create();
        CrmConnection::factory()->create(['tenant_id' => $tenant->id]);
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->post('/cabinet/knowledge-crm/sync');

        $this->actingAs($owner)->getJson('/cabinet/knowledge-crm/status')
            ->assertOk()
            ->assertJson(['state' => 'running']);
    }

    public function test_page_renders_for_max_plan(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->get('/cabinet/knowledge-crm')->assertOk();
    }

    public function test_standard_plan_forbidden(): void
    {
        $tenant = Tenant::factory()->standard()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->get('/cabinet/knowledge-crm')->assertForbidden();
    }
}
