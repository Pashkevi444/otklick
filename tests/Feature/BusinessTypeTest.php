<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Shared\Models\BusinessType;
use App\Shared\Models\Tenant;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Справочник типов бизнеса (business_types) и назначение типа тенанту супер-админом
 * и самим бизнесом (профиль).
 */
final class BusinessTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_table_is_seeded(): void
    {
        // Базовые ниши + добавленные миграциями (справочник растёт).
        $this->assertGreaterThanOrEqual(6, BusinessType::count());
        $this->assertDatabaseHas('business_types', ['key' => 'barbershop', 'label' => 'Барбершоп']);
        $this->assertDatabaseHas('business_types', ['key' => 'salon']);
    }

    public function test_super_admin_sets_tenant_business_type(): void
    {
        $su = User::factory()->superAdmin()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($su)->put("/admin/tenants/{$tenant->id}/business-type", ['business_type' => 'nails'])
            ->assertRedirect();

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'business_type' => 'nails']);

        // Сброс в «не задан».
        $this->actingAs($su)->put("/admin/tenants/{$tenant->id}/business-type", ['business_type' => null])
            ->assertRedirect();
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'business_type' => null]);
    }

    public function test_invalid_business_type_rejected(): void
    {
        $su = User::factory()->superAdmin()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($su)->put("/admin/tenants/{$tenant->id}/business-type", ['business_type' => 'spaceship'])
            ->assertSessionHasErrors('business_type');
    }

    public function test_owner_changes_own_business_type_in_profile(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->put('/cabinet/profile/business-type', ['business_type' => 'beauty'])
            ->assertRedirect(route('cabinet.profile.edit'));

        $this->assertSame('beauty', $tenant->fresh()->business_type);
    }

    public function test_profile_edit_exposes_business_type_and_options(): void
    {
        $tenant = Tenant::factory()->create(['business_type' => 'tattoo']);
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->get('/cabinet/profile')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('businessType', 'tattoo')
                ->has('businessTypes', BusinessType::count()));
    }
}
