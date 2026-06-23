<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Enums\UserRole;
use App\Models\GridView;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantInitializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class GridViewTest extends TestCase
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

    private function member(Tenant $tenant): User
    {
        return User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Member->value, 'permissions' => ['deals']]);
    }

    private function makeView(Tenant $tenant, User $user): GridView
    {
        return $this->app->make(TenantInitializer::class)->run($tenant->id, fn (): GridView => GridView::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'entity' => 'deal',
            'name' => 'Мой вид',
            'config' => ['columns' => ['title'], 'filters' => [], 'sort' => null],
        ]));
    }

    public function test_user_saves_a_view(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/grid-views', [
            'entity' => 'deal',
            'name' => 'Горящие',
            'config' => ['columns' => ['title', 'value'], 'filters' => [['field' => 'value', 'op' => 'gte', 'value' => 1000]], 'sort' => ['field' => 'value', 'dir' => 'desc']],
        ])->assertRedirect();

        $this->assertDatabaseHas('grid_views', ['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'entity' => 'deal', 'name' => 'Горящие']);
    }

    public function test_views_exposed_on_deals_index(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $this->makeView($tenant, $owner);

        $this->actingAs($owner)
            ->get('/cabinet/deals')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('views', 1));
    }

    public function test_user_updates_own_view(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $view = $this->makeView($tenant, $owner);

        $this->actingAs($owner)->put("/cabinet/grid-views/{$view->id}", [
            'name' => 'Переименован',
            'config' => ['columns' => ['title'], 'filters' => [], 'sort' => null],
        ])->assertRedirect();

        $this->assertSame('Переименован', $view->fresh()->name);
    }

    public function test_user_cannot_touch_another_users_view(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $view = $this->makeView($tenant, $owner);
        $other = $this->member($tenant);

        $this->actingAs($other)->delete("/cabinet/grid-views/{$view->id}")->assertForbidden();
        $this->assertDatabaseHas('grid_views', ['id' => $view->id]);
    }

    public function test_view_of_another_tenant_is_not_accessible(): void
    {
        [, $owner] = $this->tenantWithOwner();
        $other = Tenant::factory()->max()->create();
        $foreign = $this->makeView($other, User::factory()->owner($other)->create());

        $this->actingAs($owner)->delete("/cabinet/grid-views/{$foreign->id}")->assertNotFound();
    }

    public function test_config_is_required(): void
    {
        [, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/grid-views', ['entity' => 'deal', 'name' => 'X'])->assertSessionHasErrors('config');
    }
}
