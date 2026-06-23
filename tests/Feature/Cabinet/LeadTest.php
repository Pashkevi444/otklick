<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Enums\CrmSource;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantInitializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class LeadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantWithOwner(): array
    {
        $tenant = Tenant::factory()->max()->create();

        return [$tenant, User::factory()->owner($tenant)->create()];
    }

    /**
     * @param  list<string>  $permissions
     */
    private function member(Tenant $tenant, array $permissions): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::Member->value,
            'permissions' => $permissions,
        ]);
    }

    private function makeLead(Tenant $tenant, array $attrs = []): Lead
    {
        return $this->app->make(TenantInitializer::class)->run($tenant->id, fn (): Lead => Lead::create(array_merge([
            'tenant_id' => $tenant->id,
            'status' => LeadStatus::New,
            'source' => CrmSource::Manual,
            'title' => 'Заявка',
        ], $attrs)));
    }

    public function test_index_renders_leads_list(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $this->makeLead($tenant);

        $this->actingAs($owner)
            ->get('/cabinet/leads')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/Leads/Index')
                ->has('leads', 1)
                ->has('clients'));
    }

    public function test_owner_creates_manual_lead(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)
            ->post('/cabinet/leads', ['title' => 'С сайта', 'notes' => 'хочет КП'])
            ->assertRedirect();

        $this->assertDatabaseHas('leads', ['tenant_id' => $tenant->id, 'title' => 'С сайта', 'source' => 'manual', 'status' => 'new']);
    }

    public function test_owner_converts_lead_to_deal(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $lead = $this->makeLead($tenant);

        $this->actingAs($owner)
            ->post("/cabinet/leads/{$lead->id}/convert")
            ->assertRedirect(route('cabinet.deals.index'));

        $this->assertSame(1, Deal::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $fresh = $lead->fresh();
        $this->assertNotNull($fresh->deal_id);
        $this->assertSame(LeadStatus::Converted, $fresh->status);
    }

    public function test_owner_dismisses_lead(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $lead = $this->makeLead($tenant);

        $this->actingAs($owner)
            ->put("/cabinet/leads/{$lead->id}", ['status' => 'dismissed'])
            ->assertRedirect();

        $this->assertSame(LeadStatus::Dismissed, $lead->fresh()->status);
    }

    public function test_section_requires_leads_permission(): void
    {
        [$tenant] = $this->tenantWithOwner();

        $this->actingAs($this->member($tenant, ['conversations']))->get('/cabinet/leads')->assertForbidden();
        $this->actingAs($this->member($tenant, ['leads']))->get('/cabinet/leads')->assertOk();
    }

    public function test_viewer_without_edit_cannot_convert(): void
    {
        [$tenant] = $this->tenantWithOwner();
        $lead = $this->makeLead($tenant);

        // Доступ к разделу есть, права-действия leads.edit — нет.
        $this->actingAs($this->member($tenant, ['leads']))
            ->post("/cabinet/leads/{$lead->id}/convert")
            ->assertForbidden();

        $this->assertNull($lead->fresh()->deal_id);
    }

    public function test_lead_of_another_tenant_is_not_accessible(): void
    {
        [, $owner] = $this->tenantWithOwner();

        $other = Tenant::factory()->max()->create();
        $foreign = $this->makeLead($other);

        $this->actingAs($owner)->delete("/cabinet/leads/{$foreign->id}")->assertNotFound();
    }

    public function test_crm_section_hidden_without_max_plan(): void
    {
        $tenant = Tenant::factory()->create(); // trial — без crm
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->get('/cabinet/leads')->assertForbidden();
    }
}
