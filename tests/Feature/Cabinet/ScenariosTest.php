<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Models\Flow;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ScenariosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantWithOwner(): array
    {
        $tenant = Tenant::factory()->max()->create(); // тариф с конструктором сценариев

        return [$tenant, User::factory()->owner($tenant)->create()];
    }

    public function test_index_renders(): void
    {
        [, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)
            ->get('/cabinet/scenarios')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/Scenarios/Index')
                ->has('flows')
                ->has('actionOptions'));
    }

    public function test_store_creates_flow_for_tenant(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/scenarios', [
            'name' => 'Акция',
            'is_active' => true,
            'triggers' => ['акция', 'скидка'],
            'definition' => ['start' => 'n1', 'nodes' => ['n1' => ['type' => 'message', 'text' => 'Привет', 'action' => 'none', 'options' => []]]],
        ])->assertRedirect(route('cabinet.scenarios.index'))->assertSessionHas('success');

        $flow = Flow::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('Акция', $flow->name);
        $this->assertTrue($flow->is_active);
        $this->assertSame(['акция', 'скидка'], $flow->triggers);
    }

    public function test_toggle_flips_active(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $flow = Flow::factory()->for($tenant)->create(['is_active' => false]);

        $this->actingAs($owner)->post("/cabinet/scenarios/{$flow->id}/toggle")->assertRedirect();

        $this->assertTrue($flow->fresh()->is_active);
    }

    public function test_destroy_removes_flow(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $flow = Flow::factory()->for($tenant)->create();

        $this->actingAs($owner)->delete("/cabinet/scenarios/{$flow->id}")->assertRedirect();

        $this->assertDatabaseMissing('flows', ['id' => $flow->id]);
    }

    public function test_gated_off_plan_forbidden(): void
    {
        $tenant = Tenant::factory()->create(); // тариф без flows
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->get('/cabinet/scenarios')->assertForbidden();
    }
}
