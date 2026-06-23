<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\DashboardCardState;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Состояния плашек дашборда (глобально от СУ): редактирование, блокировка раздела
 * по «тех. работам», доступ только супер-админу.
 */
final class DashboardCardStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sets_states(): void
    {
        $su = User::factory()->superAdmin()->create();

        $this->actingAs($su)->put('/admin/dashboard-cards', [
            'states' => ['channels' => 'maintenance', 'profile' => 'new'],
        ])->assertRedirect();

        $this->assertDatabaseHas('dashboard_card_states', ['card_key' => 'channels', 'state' => 'maintenance']);
        $this->assertDatabaseHas('dashboard_card_states', ['card_key' => 'profile', 'state' => 'new']);
    }

    public function test_none_removes_the_row(): void
    {
        DashboardCardState::create(['card_key' => 'channels', 'state' => 'maintenance']);
        $su = User::factory()->superAdmin()->create();

        $this->actingAs($su)->put('/admin/dashboard-cards', ['states' => ['channels' => 'none']])->assertRedirect();

        $this->assertDatabaseMissing('dashboard_card_states', ['card_key' => 'channels']);
    }

    public function test_maintenance_blocks_the_section_with_403(): void
    {
        DashboardCardState::create(['card_key' => 'channels', 'state' => 'maintenance']);

        // Макс-тариф: раздел не закрыт тарифом — блокирует именно «тех. работы».
        $owner = User::factory()->owner(Tenant::factory()->max()->create())->create();

        $this->actingAs($owner)->get('/cabinet/channels')->assertForbidden();
        // Раздел без «тех. работ» открывается как обычно.
        $this->actingAs($owner)->get('/cabinet/profile')->assertOk();
    }

    public function test_only_super_admin_opens_editor(): void
    {
        $owner = User::factory()->owner(Tenant::factory()->create())->create();

        $this->actingAs($owner)->get('/admin/dashboard-cards')->assertForbidden();
        $this->actingAs($owner)->put('/admin/dashboard-cards', ['states' => []])->assertForbidden();
    }

    public function test_crm_cards_are_in_catalog_and_maintenance_blocks_them(): void
    {
        $su = User::factory()->superAdmin()->create();

        // Новые CRM-плашки доступны в каталоге редактора СУ.
        $this->actingAs($su)
            ->get('/admin/dashboard-cards')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('cards', fn ($cards) => collect($cards)->pluck('key')->contains('leads')
                    && collect($cards)->pluck('key')->contains('deals')));

        // «Тех. работы» на плашке «Сделки» закрывают раздел (на тарифе с CRM).
        DashboardCardState::create(['card_key' => 'deals', 'state' => 'maintenance']);
        $owner = User::factory()->owner(Tenant::factory()->max()->create())->create();
        $this->actingAs($owner)->get('/cabinet/deals')->assertForbidden();
    }
}
