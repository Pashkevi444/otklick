<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class AdminTenantManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    public function test_super_admin_creates_tenant_with_owner(): void
    {
        $response = $this->actingAs($this->superAdmin())->post('/admin/tenants', [
            'name' => 'Барбершоп Бруно',
            'plan' => 'standard',
            'access_expires_at' => '2030-01-01',
            'owner_name' => 'Иван',
            'owner_email' => 'ivan@biz.ru',
            'owner_password' => 'secret-pass',
        ]);

        $tenant = Tenant::where('name', 'Барбершоп Бруно')->firstOrFail();

        $response->assertRedirect(route('admin.tenants.show', $tenant->id));
        $this->assertSame('standard', $tenant->plan->value);
        $this->assertDatabaseHas('users', [
            'email' => 'ivan@biz.ru',
            'role' => 'owner',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_super_admin_updates_subscription(): void
    {
        $tenant = Tenant::factory()->create(['plan' => 'trial']);

        $this->actingAs($this->superAdmin())->put("/admin/tenants/{$tenant->id}", [
            'plan' => 'max',
            'access_expires_at' => '2030-06-01',
        ])->assertRedirect(route('admin.tenants.show', $tenant->id));

        $tenant->refresh();
        $this->assertSame('max', $tenant->plan->value);
        $this->assertSame('2030-06-01', $tenant->access_expires_at?->toDateString());
    }

    public function test_super_admin_blocks_and_unblocks_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post("/admin/tenants/{$tenant->id}/block");
        $this->assertTrue($tenant->refresh()->is_blocked);

        $this->actingAs($admin)->post("/admin/tenants/{$tenant->id}/unblock");
        $this->assertFalse($tenant->refresh()->is_blocked);
    }

    public function test_validation_errors_on_empty_payload(): void
    {
        $this->actingAs($this->superAdmin())
            ->post('/admin/tenants', [])
            ->assertSessionHasErrors(['name', 'owner_name', 'owner_email', 'owner_password']);
    }

    public function test_duplicate_owner_email_is_rejected(): void
    {
        User::factory()->superAdmin()->create(['email' => 'taken@biz.ru']);

        $this->actingAs($this->superAdmin())->post('/admin/tenants', [
            'name' => 'X',
            'owner_name' => 'Y',
            'owner_email' => 'taken@biz.ru',
            'owner_password' => 'secret-pass',
        ])->assertSessionHasErrors('owner_email');
    }

    public function test_tenant_user_cannot_create_tenants(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->post('/admin/tenants', [
            'name' => 'Hack',
            'owner_name' => 'H',
            'owner_email' => 'h@biz.ru',
            'owner_password' => 'secret-pass',
        ])->assertForbidden();
    }

    public function test_show_renders_tenant_with_users(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->owner($tenant)->create(['email' => 'owner@biz.ru']);

        $this->actingAs($this->superAdmin())
            ->get(route('admin.tenants.show', $tenant->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Tenants/Show')
                ->where('tenant.name', $tenant->name)
                ->has('users', 1)
            );
    }
}
