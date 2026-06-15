<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Models\CrmConnection;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class CabinetIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantWithOwner(): array
    {
        // CRM-интеграции доступны на тарифе «Макс».
        $tenant = Tenant::factory()->max()->create();

        return [$tenant, User::factory()->owner($tenant)->create()];
    }

    public function test_index_renders_providers(): void
    {
        [, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)
            ->get('/cabinet/integrations')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/Integrations/Index')
                ->has('integrations', 1)
                ->where('integrations.0.provider', 'yclients')
                ->where('integrations.0.connection', null));
    }

    public function test_owner_connects_yclients(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/integrations/connect/yclients', [
            'credentials' => ['company_id' => '123456', 'api_token' => 'secret-user-token'],
        ])->assertRedirect(route('cabinet.integrations.index'))->assertSessionHas('success');

        $this->assertDatabaseHas('crm_connections', [
            'tenant_id' => $tenant->id,
            'provider' => 'yclients',
        ]);

        $connection = CrmConnection::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('123456', $connection->credential('company_id'));
        $this->assertSame('secret-user-token', $connection->credential('api_token'));

        $raw = $this->app['db']->table('crm_connections')->where('id', $connection->id)->value('credentials');
        $this->assertStringNotContainsString('secret-user-token', (string) $raw);
    }

    public function test_reconnect_replaces_previous_connection(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/integrations/connect/yclients', ['credentials' => ['company_id' => '111', 'api_token' => 't1']]);
        $this->actingAs($owner)->post('/cabinet/integrations/connect/yclients', ['credentials' => ['company_id' => '222', 'api_token' => 't2']]);

        $this->assertDatabaseCount('crm_connections', 1);
        $this->assertSame('222', CrmConnection::query()->where('tenant_id', $tenant->id)->firstOrFail()->credential('company_id'));
    }

    public function test_validation_requires_credentials(): void
    {
        [, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)
            ->post('/cabinet/integrations/connect/yclients', [])
            ->assertSessionHasErrors(['credentials.company_id', 'credentials.api_token']);
    }

    public function test_unknown_provider_is_not_found(): void
    {
        [, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)
            ->post('/cabinet/integrations/connect/unknown-crm', ['credentials' => []])
            ->assertNotFound();
    }

    public function test_verify_reports_success(): void
    {
        Http::fake(['*' => Http::response([], 200)]);
        [$tenant, $owner] = $this->tenantWithOwner();
        $connection = CrmConnection::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)
            ->post("/cabinet/integrations/{$connection->id}/verify")
            ->assertRedirect(route('cabinet.integrations.index'))
            ->assertSessionHas('success');
    }

    public function test_verify_reports_failure(): void
    {
        Http::fake(['*' => Http::response([], 401)]);
        [$tenant, $owner] = $this->tenantWithOwner();
        $connection = CrmConnection::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)
            ->post("/cabinet/integrations/{$connection->id}/verify")
            ->assertRedirect(route('cabinet.integrations.index'))
            ->assertSessionHas('error');
    }

    public function test_owner_disconnects(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $connection = CrmConnection::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)
            ->delete("/cabinet/integrations/{$connection->id}")
            ->assertRedirect(route('cabinet.integrations.index'));

        $this->assertDatabaseMissing('crm_connections', ['id' => $connection->id]);
    }

    public function test_owner_cannot_touch_another_tenants_connection(): void
    {
        [, $owner] = $this->tenantWithOwner();
        $otherTenant = Tenant::factory()->create();
        $foreign = CrmConnection::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->actingAs($owner)->delete("/cabinet/integrations/{$foreign->id}")->assertNotFound();
        $this->assertDatabaseHas('crm_connections', ['id' => $foreign->id]);
    }

    public function test_super_admin_cannot_access(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/cabinet/integrations')->assertForbidden();
    }
}
