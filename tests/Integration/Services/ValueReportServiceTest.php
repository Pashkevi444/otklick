<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Modules\Analytics\DTO\AnalyticsRange;
use App\Modules\Analytics\DTO\MetricCard;
use App\Modules\Analytics\DTO\ValueReport;
use App\Modules\Analytics\Services\ValueReportService;
use App\Modules\Booking\Models\CrmConnection;
use App\Modules\Conversations\Models\Conversation;
use App\Shared\Models\Tenant;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Сквозная проверка реальных запросов (bookingsForCrm/cancelled/leadsCount) на
 * одной CRM. Разделение по нескольким CRM и математику метрик проверяет
 * unit-тест ({@see \Tests\Unit\Services\ValueReportServiceTest}).
 */
final class ValueReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function booking(Tenant $tenant, string $crmConnectionId, ?string $title, ?int $price, array $reminders = []): void
    {
        Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'crm_connection_id' => $crmConnectionId,
            'booked_at' => now(),
            'booked_service_title' => $title,
            'booked_service_price' => $price,
            'reminders_sent' => $reminders,
        ]);
    }

    private function card(ValueReport $report, string $key): MetricCard
    {
        foreach ($report->kpis as $card) {
            if ($card->key === $key) {
                return $card;
            }
        }

        $this->fail("Нет KPI «{$key}» в отчёте.");
    }

    public function test_value_report_aggregates_revenue_for_a_crm(): void
    {
        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);

        $crm = CrmConnection::factory()->create(['tenant_id' => $tenant->id, 'credentials' => ['company_id' => '12345']]);

        $this->booking($tenant, $crm->id, 'Маникюр', 1500, [60, 1440]);
        $this->booking($tenant, $crm->id, 'Стрижка', 2000, [60]);
        $this->booking($tenant, $crm->id, 'Консультация', null);
        Conversation::factory()->create(['tenant_id' => $tenant->id, 'crm_connection_id' => $crm->id, 'cancelled_at' => now()]);
        Conversation::factory()->create(['tenant_id' => $tenant->id]); // лид без записи → знаменатель конверсии

        $reports = $this->app->make(ValueReportService::class)->reportsForPeriod(AnalyticsRange::resolve(null, null, null));

        $this->assertCount(1, $reports);
        $report = $reports[0];

        $this->assertSame('YClients · #12345', $report->crmLabel);
        $this->assertSame(3500, $this->card($report, 'revenue')->value);
        $this->assertSame(3, $this->card($report, 'bookings')->value);
        $this->assertSame(1750, $this->card($report, 'avg_check')->value);
        $this->assertSame(3, $this->card($report, 'reminders')->value);
        $this->assertSame(1, $this->card($report, 'cancelled')->value);
        $this->assertSame(60.0, $this->card($report, 'conversion')->value); // 3 записи / 5 лидов
        $this->assertNotNull($report->note);
        $this->assertSame('Стрижка', $report->topServices[0]->title);
    }

    public function test_no_crm_means_no_reports(): void
    {
        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);

        $reports = $this->app->make(ValueReportService::class)->reportsForPeriod(AnalyticsRange::resolve(null, null, null));

        $this->assertSame([], $reports);
    }
}
