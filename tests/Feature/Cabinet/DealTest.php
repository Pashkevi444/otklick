<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DealService;
use App\Tenancy\TenantInitializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class DealTest extends TestCase
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

    private function firstStageId(Tenant $tenant): string
    {
        $this->app->make(TenantInitializer::class)->run($tenant->id, fn () => $this->app->make(DealService::class)->ensureStages());

        return (string) DealStage::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('sort_order')->firstOrFail()->id;
    }

    public function test_index_seeds_stages_and_renders_kanban(): void
    {
        [, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)
            ->get('/cabinet/deals')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/Deals/Index')
                ->has('stages', 5)
                ->has('deals')
                ->has('clients')
                ->has('team'));
    }

    public function test_owner_creates_manual_deal(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $stageId = $this->firstStageId($tenant);

        $this->actingAs($owner)
            ->post('/cabinet/deals', ['stage_id' => $stageId, 'title' => 'Заявка на КП', 'value' => 50000])
            ->assertRedirect();

        $this->assertDatabaseHas('deals', ['tenant_id' => $tenant->id, 'title' => 'Заявка на КП', 'value' => 50000, 'source' => 'manual']);
    }

    public function test_owner_moves_deal_between_stages(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $this->firstStageId($tenant);
        $stages = DealStage::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('sort_order')->get();

        $this->actingAs($owner)->post('/cabinet/deals', ['stage_id' => $stages[0]->id, 'title' => 'Сделка']);
        $deal = Deal::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->actingAs($owner)
            ->put("/cabinet/deals/{$deal->id}", ['stage_id' => $stages[2]->id])
            ->assertRedirect();

        $this->assertSame($stages[2]->id, $deal->fresh()->stage_id);
    }

    public function test_section_requires_deals_permission(): void
    {
        [$tenant] = $this->tenantWithOwner();

        $this->actingAs($this->member($tenant, ['conversations']))->get('/cabinet/deals')->assertForbidden();
        $this->actingAs($this->member($tenant, ['deals']))->get('/cabinet/deals')->assertOk();
    }

    public function test_deal_of_another_tenant_is_not_accessible(): void
    {
        [, $owner] = $this->tenantWithOwner();

        $other = Tenant::factory()->max()->create();
        $stageId = $this->firstStageId($other);
        $ownerOther = User::factory()->owner($other)->create();
        $this->actingAs($ownerOther)->post('/cabinet/deals', ['stage_id' => $stageId, 'title' => 'Чужой']);
        $foreign = Deal::withoutGlobalScopes()->where('tenant_id', $other->id)->firstOrFail();

        $this->actingAs($owner)->delete("/cabinet/deals/{$foreign->id}")->assertNotFound();
    }
}
