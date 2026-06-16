<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class CabinetAccessTest extends TestCase
{
    use RefreshDatabase;

    private function owner(Tenant $tenant): User
    {
        return User::factory()->owner($tenant)->create();
    }

    public function test_active_tenant_can_open_cabinet(): void
    {
        $tenant = Tenant::factory()->create(['is_blocked' => false, 'access_expires_at' => null]);

        $this->actingAs($this->owner($tenant))->get('/cabinet')->assertOk();
    }

    public function test_blocked_tenant_is_redirected_to_suspended(): void
    {
        $tenant = Tenant::factory()->create(['is_blocked' => true]);

        $this->actingAs($this->owner($tenant))->get('/cabinet')->assertRedirect(route('suspended'));
    }

    public function test_expired_tenant_is_redirected_to_suspended(): void
    {
        $tenant = Tenant::factory()->create(['access_expires_at' => now()->subDay()]);

        $this->actingAs($this->owner($tenant))->get('/cabinet/channels')->assertRedirect(route('suspended'));
    }

    public function test_suspended_page_renders_for_blocked_tenant(): void
    {
        $tenant = Tenant::factory()->create(['is_blocked' => true]);

        $this->actingAs($this->owner($tenant))
            ->get('/suspended')
            ->assertOk();
    }

    public function test_suspended_redirects_active_tenant_to_dashboard(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->owner($tenant))
            ->get('/suspended')
            ->assertRedirect(route('cabinet.dashboard'));
    }

    public function test_dashboard_renders_as_hub(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->owner($tenant))
            ->get('/cabinet')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Cabinet/Dashboard'));
    }

    public function test_billing_page_renders(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->owner($tenant))
            ->get('/cabinet/billing')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Cabinet/Billing'));
    }
}
