<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class AnalyticsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_page_exposes_lead_metrics(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();

        Conversation::factory()->count(3)->create(['tenant_id' => $tenant->id, 'created_at' => now()->subDay()]);
        Conversation::factory()->count(2)->create(['tenant_id' => Tenant::factory()->create()->id, 'created_at' => now()->subDay()]);

        $this->actingAs($owner)
            ->get('/cabinet/analytics')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/Analytics')
                ->has('analytics.kpis', 6)
                ->where('analytics.totals.leads', 3)
                ->has('analytics.daily')
                ->has('insights'));
    }

    public function test_dashboard_is_a_hub_without_analytics(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)
            ->get('/cabinet')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Cabinet/Dashboard')->missing('analytics'));
    }

    public function test_period_filter_changes_window(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();

        Conversation::factory()->create(['tenant_id' => $tenant->id, 'created_at' => now()->subDay()]);
        Conversation::factory()->create(['tenant_id' => $tenant->id, 'created_at' => now()->subDays(45)]);

        $this->actingAs($owner)
            ->get('/cabinet/analytics?period=7d')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('analytics.period.key', '7d')
                ->where('analytics.totals.leads', 1));

        $this->actingAs($owner)
            ->get('/cabinet/analytics?period=90d')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('analytics.totals.leads', 2));
    }

    public function test_custom_date_range_window(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();

        Conversation::factory()->create(['tenant_id' => $tenant->id, 'created_at' => '2026-05-10 12:00:00']);
        Conversation::factory()->create(['tenant_id' => $tenant->id, 'created_at' => '2026-01-01 12:00:00']);

        $this->actingAs($owner)
            ->get('/cabinet/analytics?from=2026-05-01&to=2026-05-31')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('analytics.period.key', 'custom')
                ->where('analytics.period.from', '2026-05-01')
                ->where('analytics.period.to', '2026-05-31')
                ->where('analytics.totals.leads', 1));
    }

    public function test_refresh_insights_button_populates_ai_block(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();
        Conversation::factory()->create(['tenant_id' => $tenant->id, 'created_at' => now()->subDay()]);

        // Кнопка обновления — синхронный пересчёт.
        $this->actingAs($owner)
            ->post('/cabinet/analytics/insights/refresh', ['period' => '30d'])
            ->assertRedirect();

        // Теперь разбор закэширован (FakeLlm не отдаёт JSON → фолбек на правила).
        $this->actingAs($owner)
            ->get('/cabinet/analytics?period=30d')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('insights.source', 'rules')
                ->has('insights.items'));
    }

    public function test_exports_leads_csv(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();
        Conversation::factory()->create(['tenant_id' => $tenant->id, 'contact_phone' => '+79991112233', 'created_at' => now()->subDay()]);

        $response = $this->actingAs($owner)->get('/cabinet/analytics/export/leads');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('+79991112233', $response->streamedContent());
    }

    public function test_unknown_export_type_is_404(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->get('/cabinet/analytics/export/bogus')->assertNotFound();
    }
}
