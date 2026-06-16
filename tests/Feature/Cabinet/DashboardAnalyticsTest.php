<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_exposes_lead_analytics(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        Conversation::factory()->count(3)->create(['tenant_id' => $tenant->id, 'created_at' => now()->subDay()]);

        // Чужой тенант — в аналитику не попадает.
        Conversation::factory()->count(2)->create(['tenant_id' => Tenant::factory()->create()->id, 'created_at' => now()->subDay()]);

        $this->actingAs($owner)
            ->get('/cabinet')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/Dashboard')
                ->has('analytics.kpis', 6)
                ->where('analytics.totals.leads', 3)
                ->has('analytics.daily')
                ->has('analytics.gaps')
                ->has('analytics.recent', 3));
    }

    public function test_period_filter_changes_window(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        Conversation::factory()->create(['tenant_id' => $tenant->id, 'created_at' => now()->subDay()]);
        Conversation::factory()->create(['tenant_id' => $tenant->id, 'created_at' => now()->subDays(45)]);

        // 7 дней — только свежий лид.
        $this->actingAs($owner)
            ->get('/cabinet?period=7d')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('analytics.period.key', '7d')
                ->where('analytics.totals.leads', 1));

        // 90 дней — оба.
        $this->actingAs($owner)
            ->get('/cabinet?period=90d')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('analytics.totals.leads', 2));
    }

    public function test_exports_leads_csv(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'contact_phone' => '+79991112233',
            'created_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($owner)->get('/cabinet/analytics/export/leads');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('+79991112233', $response->streamedContent());
    }

    public function test_exports_daily_csv(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $response = $this->actingAs($owner)->get('/cabinet/analytics/export/daily');

        $response->assertOk();
        $this->assertStringContainsString('Лидов', $response->streamedContent());
    }

    public function test_unknown_export_type_is_404(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->get('/cabinet/analytics/export/bogus')->assertNotFound();
    }
}
