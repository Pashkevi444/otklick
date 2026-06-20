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

    public function test_store_preserves_input_and_condition_nodes(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/scenarios', [
            'name' => 'Анкета',
            'is_active' => true,
            'triggers' => ['анкета'],
            'definition' => ['start' => 'n1', 'nodes' => [
                'n1' => ['type' => 'input', 'text' => 'Как вас зовут?', 'variable' => 'name', 'next' => 'n2'],
                'n2' => ['type' => 'condition', 'variable' => 'name', 'operator' => 'contains', 'value' => 'иван', 'next' => 'n3', 'else' => 'n3'],
                'n3' => ['type' => 'message', 'text' => 'Привет, {{name}}', 'action' => 'end', 'options' => []],
            ]],
        ])->assertRedirect()->assertSessionHas('success');

        $flow = Flow::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $nodes = $flow->definition['nodes'];
        $this->assertSame('input', $nodes['n1']['type']);
        $this->assertSame('name', $nodes['n1']['variable']);
        $this->assertSame('condition', $nodes['n2']['type']);
        $this->assertSame('contains', $nodes['n2']['operator']);
        $this->assertSame('n3', $nodes['n2']['else']);
    }

    public function test_store_preserves_canvas_positions(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/scenarios', [
            'name' => 'Схема',
            'is_active' => true,
            'triggers' => ['привет'],
            'definition' => ['start' => 'n1', 'nodes' => [
                'n1' => ['type' => 'message', 'text' => 'Привет', 'action' => 'end', 'options' => [], 'position' => ['x' => 120, 'y' => 40]],
            ]],
        ])->assertRedirect()->assertSessionHas('success');

        $flow = Flow::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(120, $flow->definition['nodes']['n1']['position']['x']);
        $this->assertSame(40, $flow->definition['nodes']['n1']['position']['y']);
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
