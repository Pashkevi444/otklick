<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Models\Conversation;
use App\Models\CrmConnection;
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

    public function test_ai_insights_available_on_max(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)
            ->get('/cabinet/analytics')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('aiInsights', true));
    }

    public function test_ai_insights_hidden_when_feature_disabled(): void
    {
        // Аналитика доступна, но ИИ-рекомендации выключены оверрайдом — блок скрыт,
        // ИИ не считается, остаётся общая аналитика.
        $tenant = Tenant::factory()->max()->create(['settings' => ['overrides' => ['aiInsights' => false]]]);
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)
            ->get('/cabinet/analytics')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('aiInsights', false)
                ->where('insights', null)
                ->has('analytics.kpis', 6));

        // Ручной пересчёт ИИ-разбора тоже закрыт.
        $this->actingAs($owner)
            ->post('/cabinet/analytics/insights/refresh', ['period' => '30d'])
            ->assertForbidden();
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

    public function test_value_report_exposed_per_crm(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();
        $crm = CrmConnection::factory()->create(['tenant_id' => $tenant->id, 'credentials' => ['company_id' => '777']]);

        Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'crm_connection_id' => $crm->id,
            'booked_at' => now()->subDay(),
            'booked_service_title' => 'Маникюр',
            'booked_service_price' => 1500,
        ]);

        $this->actingAs($owner)
            ->get('/cabinet/analytics')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('valueReports', 1)
                ->where('valueReports.0.crmConnectionId', $crm->id)
                ->where('valueReports.0.crmLabel', 'YClients · #777')
                ->has('valueReports.0.kpis', 6));
    }

    public function test_value_report_hidden_when_crm_feature_disabled(): void
    {
        // Аналитика разрешена, но интеграция с CRM выключена оверрайдом —
        // CRM-отчёт не показываем, остаётся только общая аналитика.
        $tenant = Tenant::factory()->max()->create(['settings' => ['overrides' => ['crm' => false]]]);
        $owner = User::factory()->owner($tenant)->create();
        $crm = CrmConnection::factory()->create(['tenant_id' => $tenant->id]);
        Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'crm_connection_id' => $crm->id,
            'booked_at' => now()->subDay(),
            'booked_service_price' => 1000,
        ]);

        $this->actingAs($owner)
            ->get('/cabinet/analytics')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('analytics.kpis', 6) // общая аналитика на месте
                ->has('valueReports', 0));  // CRM-отчёта нет

        $this->actingAs($owner)->get("/cabinet/analytics/export/value?crm={$crm->id}")->assertForbidden();
    }

    public function test_no_crm_means_empty_value_reports(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)
            ->get('/cabinet/analytics')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('valueReports', 0));
    }

    public function test_exports_value_csv_for_a_crm(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();
        $crm = CrmConnection::factory()->create(['tenant_id' => $tenant->id]);

        Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'crm_connection_id' => $crm->id,
            'booked_at' => now()->subDay(),
            'booked_service_title' => 'Стрижка',
            'booked_service_price' => 2000,
        ]);

        $response = $this->actingAs($owner)->get("/cabinet/analytics/export/value?crm={$crm->id}");

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Стрижка', $content);
        $this->assertStringContainsString('2000', $content);
    }

    public function test_value_export_without_crm_is_404(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->get('/cabinet/analytics/export/value')->assertNotFound();
    }
}
