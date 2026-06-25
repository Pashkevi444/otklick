<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Shared\Models\Tenant;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_enter_and_leave_business_cabinet(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        // Войти в кабинет бизнеса.
        $this->actingAs($superAdmin)
            ->post("/admin/tenants/{$tenant->id}/impersonate")
            ->assertRedirect('/cabinet');
        $this->assertAuthenticatedAs($owner);

        // Выйти обратно — снова супер-админ.
        $this->post('/impersonate/leave')->assertRedirect(route('admin.tenants.index'));
        $this->assertAuthenticatedAs($superAdmin);
    }

    public function test_owner_cannot_impersonate(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->post("/admin/tenants/{$tenant->id}/impersonate")->assertForbidden();
    }
}
