<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Enums\UserRole;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class MemberPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function member(Tenant $tenant, array $permissions): User
    {
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => UserRole::Member->value,
            'permissions' => $permissions,
        ]);
    }

    private function lead(Tenant $tenant): Conversation
    {
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);

        return Conversation::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);
    }

    public function test_owner_deletes_lead_from_grid(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        $lead = $this->lead($tenant);

        $this->actingAs($owner)->delete("/cabinet/conversations/{$lead->id}")->assertRedirect();

        $this->assertDatabaseMissing('conversations', ['id' => $lead->id]);
    }

    public function test_deleting_lead_from_detail_page_redirects_to_grid(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        $lead = $this->lead($tenant);

        // Удаление со страницы самого диалога не должно вести на удалённую страницу.
        $this->actingAs($owner)
            ->from("/cabinet/conversations/{$lead->id}")
            ->delete("/cabinet/conversations/{$lead->id}")
            ->assertRedirect('/cabinet/conversations');

        $this->assertDatabaseMissing('conversations', ['id' => $lead->id]);
    }

    public function test_member_without_delete_right_cannot_delete_lead(): void
    {
        $tenant = Tenant::factory()->create();
        $member = $this->member($tenant, ['conversations']); // доступ к разделу есть, права на удаление — нет
        $lead = $this->lead($tenant);

        $this->actingAs($member)->delete("/cabinet/conversations/{$lead->id}")->assertForbidden();

        $this->assertDatabaseHas('conversations', ['id' => $lead->id]);
    }

    public function test_member_with_delete_right_deletes_lead(): void
    {
        $tenant = Tenant::factory()->create();
        $member = $this->member($tenant, ['conversations', 'conversations.delete']);
        $lead = $this->lead($tenant);

        $this->actingAs($member)->delete("/cabinet/conversations/{$lead->id}")->assertRedirect();

        $this->assertDatabaseMissing('conversations', ['id' => $lead->id]);
    }

    public function test_member_without_edit_right_cannot_change_lead_status(): void
    {
        $tenant = Tenant::factory()->create();
        $member = $this->member($tenant, ['conversations']);
        $lead = $this->lead($tenant);

        $this->actingAs($member)
            ->put("/cabinet/conversations/{$lead->id}/status", ['outcome' => 'spam'])
            ->assertForbidden();
    }

    public function test_member_with_edit_right_changes_lead_status(): void
    {
        $tenant = Tenant::factory()->create();
        $member = $this->member($tenant, ['conversations', 'conversations.edit']);
        $lead = $this->lead($tenant);

        $this->actingAs($member)
            ->put("/cabinet/conversations/{$lead->id}/status", ['outcome' => 'spam'])
            ->assertRedirect();
    }

    public function test_clients_actions_gated_by_member_rights(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        // Доступ к разделу есть, прав на действия нет.
        $viewer = $this->member($tenant, ['clients']);
        $this->actingAs($viewer)->put("/cabinet/clients/{$client->id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->delete("/cabinet/clients/{$client->id}")->assertForbidden();

        // С правами — можно.
        $editor = $this->member($tenant, ['clients', 'clients.edit', 'clients.delete']);
        $this->actingAs($editor)->put("/cabinet/clients/{$client->id}", ['name' => 'Новое'])->assertRedirect();
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Новое']);
        $this->actingAs($editor)->delete("/cabinet/clients/{$client->id}")->assertRedirect();
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    public function test_owner_cannot_grant_permission_outside_plan(): void
    {
        // Тариф без clientBase — раздел/действия клиентов не выдаются (матрица
        // мемберов ⊆ тенантной матрицы), даже если ключи присланы.
        $tenant = Tenant::factory()->create(); // trial: clientBase = false
        $owner = User::factory()->owner($tenant)->create();
        $member = $this->member($tenant, []);

        $this->actingAs($owner)
            ->put("/cabinet/team/{$member->id}", ['permissions' => ['conversations', 'clients', 'clients.delete']])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(['conversations'], $member->fresh()->permissions);
    }

    public function test_team_permission_groups_limited_by_plan(): void
    {
        $owner = User::factory()->owner(Tenant::factory()->create())->create(); // trial
        $this->actingAs($owner)
            ->get('/cabinet/team')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('permissionGroups', 6)); // без clients/analytics/integrations

        $maxOwner = User::factory()->owner(Tenant::factory()->max()->create())->create();
        $this->actingAs($maxOwner)
            ->get('/cabinet/team')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('permissionGroups', 9)); // все разделы
    }
}
