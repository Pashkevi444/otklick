<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Shared\Enums\UserRole;
use App\Shared\Models\Tenant;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TeamTest extends TestCase
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

    public function test_owner_adds_member_with_permissions(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->post('/cabinet/team', [
            'name' => 'Оператор',
            'email' => 'op@biz.ru',
            'password' => 'secret-pass',
            'permissions' => ['conversations', 'knowledge'],
        ])->assertRedirect(route('cabinet.team.index'));

        $member = User::query()->where('email', 'op@biz.ru')->firstOrFail();
        $this->assertSame(UserRole::Member, $member->role);
        $this->assertSame(['conversations', 'knowledge'], $member->permissions);
    }

    public function test_member_limit_enforced(): void
    {
        $tenant = Tenant::factory()->standard()->create(); // maxOperators = 2
        $owner = User::factory()->owner($tenant)->create();
        $this->member($tenant, []); // владелец + 1 = лимит

        $this->actingAs($owner)->post('/cabinet/team', [
            'name' => 'Ещё', 'email' => 'x@biz.ru', 'password' => 'secret-pass', 'permissions' => [],
        ])->assertSessionHasErrors('email');
    }

    public function test_operator_gated_to_allowed_sections(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $member = $this->member($tenant, ['conversations']);

        $this->actingAs($member)->get('/cabinet/conversations')->assertOk();
        $this->actingAs($member)->get('/cabinet/knowledge')->assertForbidden();
    }

    public function test_team_page_is_owner_only(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();
        $member = $this->member($tenant, ['conversations']);

        $this->actingAs($owner)->get('/cabinet/team')->assertOk();
        $this->actingAs($member)->get('/cabinet/team')->assertForbidden();
    }

    public function test_owner_updates_and_removes_member(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();
        $member = $this->member($tenant, ['conversations']);

        $this->actingAs($owner)->put("/cabinet/team/{$member->id}", ['permissions' => ['knowledge', 'analytics']])
            ->assertRedirect(route('cabinet.team.index'));
        $this->assertSame(['knowledge', 'analytics'], $member->fresh()->permissions);

        $this->actingAs($owner)->delete("/cabinet/team/{$member->id}")->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $member->id]);
    }

    public function test_member_cannot_add_team_members(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $member = $this->member($tenant, ['conversations']);

        $this->actingAs($member)->post('/cabinet/team', [
            'name' => 'X', 'email' => 'y@biz.ru', 'password' => 'secret-pass', 'permissions' => [],
        ])->assertForbidden();
    }
}
