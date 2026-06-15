<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class PlanGatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_plan_cannot_open_crm_integrations(): void
    {
        $tenant = Tenant::factory()->standard()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->get('/cabinet/integrations')->assertForbidden();
    }

    public function test_trial_plan_cannot_open_crm_integrations(): void
    {
        $tenant = Tenant::factory()->create(); // trial по умолчанию
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->get('/cabinet/integrations')->assertForbidden();
    }

    public function test_max_plan_can_open_crm_integrations(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->get('/cabinet/integrations')->assertOk();
    }

    public function test_subscription_page_renders_for_any_plan(): void
    {
        $tenant = Tenant::factory()->standard()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)
            ->get('/cabinet/subscription')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/Subscription')
                ->where('plan.key', 'standard')
                ->where('plan.features.crm', false));
    }
}
