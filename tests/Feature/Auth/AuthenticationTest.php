<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Shared\Models\Tenant;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/Login'));
    }

    public function test_owner_logs_in_and_is_redirected_to_cabinet(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->owner($tenant)->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('cabinet.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_super_admin_logs_in_and_is_redirected_to_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_authenticated_super_admin_visiting_login_is_redirected_to_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/login')->assertRedirect(route('admin.dashboard'));
    }

    public function test_authenticated_owner_visiting_login_is_redirected_to_cabinet(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->owner($tenant)->create();

        $this->actingAs($user)->get('/login')->assertRedirect(route('cabinet.dashboard'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_inertia_logout_returns_external_location_not_followable_redirect(): void
    {
        $user = User::factory()->superAdmin()->create();

        // Inertia-запрос (как из кабинета/админки): на кросс-доменный лендинг
        // нужен 409 + X-Inertia-Location, иначе клиент follow'ит 302 через fetch
        // и упирается в CORS («Не удалось выполнить запрос»).
        $this->actingAs($user)
            ->post('/logout', [], ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('home'));

        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login_from_protected_routes(): void
    {
        $this->get('/cabinet')->assertRedirect('/login');
        $this->get('/admin/tenants')->assertRedirect('/login');
    }

    public function test_tenant_user_cannot_access_admin_area(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->owner($tenant)->create();

        $this->actingAs($user)->get('/admin/tenants')->assertForbidden();
    }

    public function test_super_admin_can_access_admin_area(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get('/admin/tenants')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Tenants/Index'));
    }
}
