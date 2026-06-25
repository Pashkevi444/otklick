<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Modules\Clients\Models\Client;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Tenant;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ClientBanTest extends TestCase
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

    public function test_owner_bans_and_unbans_client(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)->post("/cabinet/clients/{$client->id}/ban")->assertRedirect();
        $this->assertNotNull($client->fresh()->banned_at);

        $this->actingAs($owner)->post("/cabinet/clients/{$client->id}/unban")->assertRedirect();
        $this->assertNull($client->fresh()->banned_at);
    }

    public function test_ban_requires_clients_edit_permission(): void
    {
        [$tenant] = $this->tenantWithOwner();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $viewer = $this->member($tenant, ['clients']);
        $this->actingAs($viewer)->post("/cabinet/clients/{$client->id}/ban")->assertForbidden();
        $this->assertNull($client->fresh()->banned_at);

        $editor = $this->member($tenant, ['clients', 'clients.edit']);
        $this->actingAs($editor)->post("/cabinet/clients/{$client->id}/ban")->assertRedirect();
        $this->assertNotNull($client->fresh()->banned_at);
    }

    public function test_index_and_show_expose_banned_flag(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'banned_at' => now()]);

        $this->actingAs($owner)
            ->get('/cabinet/clients')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('clients.0.banned', true));

        $this->actingAs($owner)
            ->get("/cabinet/clients/{$client->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('client.banned', true));
    }

    public function test_ban_notice_includes_business_contacts(): void
    {
        [$tenant] = $this->tenantWithOwner();
        $tenant->update(['settings' => [...$tenant->settings, 'profile' => ['phone' => '+7 900 111-22-33', 'email' => 'shop@x.ru']]]);

        $notice = $tenant->fresh()->banNotice();

        $this->assertStringContainsString('+7 900 111-22-33', $notice);
        $this->assertStringContainsString('shop@x.ru', $notice);
    }

    public function test_ban_notice_falls_back_to_owner_email(): void
    {
        $tenant = Tenant::factory()->max()->create();
        User::factory()->owner($tenant)->create(['email' => 'owner@x.ru']);

        $this->assertStringContainsString('owner@x.ru', $tenant->banNotice());
    }
}
