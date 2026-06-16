<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\TenantPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TenantOverridesTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    public function test_super_admin_overrides_rights_and_limits(): void
    {
        $tenant = Tenant::factory()->create(['plan' => TenantPlan::Standard]);

        $this->actingAs($this->superAdmin())
            ->put("/admin/tenants/{$tenant->id}/overrides", [
                'crm' => true,
                'analytics' => true,
                'broadcasts' => false,
                'clientBase' => false,
                'allChannels' => false,
                'webWidget' => true,
                'maxOperators' => 7,
                'maxNotifyEmail' => 10,
                'maxNotifyTelegram' => 50,
            ])
            ->assertRedirect();

        $tenant->refresh();
        $this->assertTrue($tenant->features()->crm);                 // Standard обычно без CRM
        $this->assertSame(10, $tenant->features()->maxNotifyEmail);
        $this->assertSame(50, $tenant->features()->maxNotifyTelegram);
    }

    public function test_super_admin_resets_overrides(): void
    {
        $tenant = Tenant::factory()->create([
            'plan' => TenantPlan::Standard,
            'settings' => ['overrides' => ['crm' => true]],
        ]);

        $this->actingAs($this->superAdmin())
            ->delete("/admin/tenants/{$tenant->id}/overrides")
            ->assertRedirect();

        $this->assertFalse($tenant->refresh()->features()->crm);
        $this->assertArrayNotHasKey('overrides', $tenant->settings ?? []);
    }

    public function test_overrides_require_super_admin(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)
            ->delete("/admin/tenants/{$tenant->id}/overrides")
            ->assertForbidden();
    }
}
