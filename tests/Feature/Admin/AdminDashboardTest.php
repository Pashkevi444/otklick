<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Дашборд супер-админки: единая точка входа со всеми разделами площадки.
 */
final class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_dashboard_with_counts(): void
    {
        $su = User::factory()->superAdmin()->create();

        $this->actingAs($su)->get('/admin')->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Admin/Dashboard')
                ->has('counts.tenants')
                ->has('counts.scenarioTemplates')
                ->has('counts.knowledgeTemplates'));
    }

    public function test_non_super_admin_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->get('/admin')->assertForbidden();
    }
}
