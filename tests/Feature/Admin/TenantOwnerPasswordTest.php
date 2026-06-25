<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Shared\Models\Tenant;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class TenantOwnerPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sets_owner_password_and_owner_can_login(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create(['email' => 'owner@biz.ru']);

        $this->actingAs($admin)->put("/admin/tenants/{$tenant->id}/owner-password", [
            'password' => 'reset-by-admin-1',
            'password_confirmation' => 'reset-by-admin-1',
        ])->assertRedirect(route('admin.tenants.show', $tenant->id));

        // Пароль владельца сменён: новый подходит, старый — нет.
        $fresh = $owner->fresh();
        $this->assertTrue(Hash::check('reset-by-admin-1', $fresh->password));
        $this->assertFalse(Hash::check('password', $fresh->password));
    }

    public function test_owner_cannot_set_owner_password(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->put("/admin/tenants/{$tenant->id}/owner-password", [
            'password' => 'hack-attempt-1',
            'password_confirmation' => 'hack-attempt-1',
        ])->assertForbidden();
    }

    public function test_password_must_be_confirmed(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $tenant = Tenant::factory()->create();
        User::factory()->owner($tenant)->create();

        $this->actingAs($admin)->from(route('admin.tenants.show', $tenant->id))
            ->put("/admin/tenants/{$tenant->id}/owner-password", [
                'password' => 'mismatch-aaaa',
                'password_confirmation' => 'mismatch-bbbb',
            ])->assertSessionHasErrors('password');
    }
}
