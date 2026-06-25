<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Modules\Analytics\DTO\AnalyticsRange;
use App\Modules\Analytics\DTO\MetricCard;
use App\Modules\Analytics\DTO\ValueReport;
use App\Modules\Analytics\Repositories\Contracts\LeadAnalyticsRepositoryInterface;
use App\Modules\Analytics\Services\ValueReportService;
use App\Modules\Booking\Contracts\BookingApi;
use App\Modules\Booking\Models\CrmConnection;
use App\Modules\Conversations\Models\Conversation;
use App\Shared\Enums\CrmProvider;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

final class ValueReportServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function connection(string $id, string $company): CrmConnection
    {
        $c = new CrmConnection;
        $c->id = $id;
        $c->provider = CrmProvider::Yclients;
        $c->credentials = ['company_id' => $company];

        return $c;
    }

    private function booking(?string $title, ?int $price, array $reminders = []): Conversation
    {
        $c = new Conversation;
        $c->booked_service_title = $title;
        $c->booked_service_price = $price;
        $c->reminders_sent = $reminders;

        return $c;
    }

    private function card(ValueReport $report, string $key): MetricCard
    {
        foreach ($report->kpis as $card) {
            if ($card->key === $key) {
                return $card;
            }
        }

        $this->fail("Нет KPI «{$key}».");
    }

    private function reportFor(array $reports, string $id): ValueReport
    {
        foreach ($reports as $report) {
            if ($report->crmConnectionId === $id) {
                return $report;
            }
        }

        $this->fail("Нет отчёта для «{$id}».");
    }

    public function test_per_crm_separation_metrics_and_deltas(): void
    {
        $range = AnalyticsRange::resolve(null, null, null);

        $connections = Mockery::mock(BookingApi::class);
        $connections->shouldReceive('forCurrentTenant')->andReturn(collect([
            $this->connection('crm-1', '111'),
            $this->connection('crm-2', '222'),
        ]));

        $repo = Mockery::mock(LeadAnalyticsRepositoryInterface::class);

        // CRM #1: текущие 3 записи (одна без цены) против 1 записи в прошлом периоде.
        $repo->shouldReceive('bookingsForCrm')->with('crm-1', $range->from, $range->to)->andReturn(collect([
            $this->booking('Маникюр', 1500, [60, 1440]),
            $this->booking('Стрижка', 2000, [60]),
            $this->booking('Консультация', null),
        ]));
        $repo->shouldReceive('bookingsForCrm')->with('crm-1', $range->previousFrom, $range->previousTo)->andReturn(collect([
            $this->booking('Маникюр', 1000),
        ]));
        $repo->shouldReceive('cancelledCountForCrm')->with('crm-1', $range->from, $range->to)->andReturn(1);
        $repo->shouldReceive('cancelledCountForCrm')->with('crm-1', $range->previousFrom, $range->previousTo)->andReturn(0);

        // CRM #2: 1 запись, прошлого нет.
        $repo->shouldReceive('bookingsForCrm')->with('crm-2', $range->from, $range->to)->andReturn(collect([
            $this->booking('Окрашивание', 5000, [60]),
        ]));
        $repo->shouldReceive('bookingsForCrm')->with('crm-2', $range->previousFrom, $range->previousTo)->andReturn(collect([]));
        $repo->shouldReceive('cancelledCountForCrm')->with('crm-2', Mockery::any(), Mockery::any())->andReturn(0);

        $repo->shouldReceive('leadsCount')->with($range->from, $range->to)->andReturn(10);
        $repo->shouldReceive('leadsCount')->with($range->previousFrom, $range->previousTo)->andReturn(8);

        $reports = (new ValueReportService($repo, $connections))->reportsForPeriod($range);

        $this->assertCount(2, $reports);

        $r1 = $this->reportFor($reports, 'crm-1');
        $this->assertSame('YClients · #111', $r1->crmLabel);
        $this->assertSame(3500, $this->card($r1, 'revenue')->value);
        $this->assertSame(250.0, $this->card($r1, 'revenue')->deltaPct);   // (3500-1000)/1000
        $this->assertSame(3, $this->card($r1, 'bookings')->value);
        $this->assertSame(1750, $this->card($r1, 'avg_check')->value);
        $this->assertSame(3, $this->card($r1, 'reminders')->value);
        $this->assertSame(1, $this->card($r1, 'cancelled')->value);
        $this->assertSame(30.0, $this->card($r1, 'conversion')->value);    // 3 / 10
        $this->assertNotNull($r1->note);
        $this->assertSame('Стрижка', $r1->topServices[0]->title);
        $this->assertSame(2000, $r1->topServices[0]->revenue);

        $r2 = $this->reportFor($reports, 'crm-2');
        $this->assertSame(5000, $this->card($r2, 'revenue')->value);
        $this->assertNull($this->card($r2, 'revenue')->deltaPct);          // прошлого периода не было
        $this->assertNull($r2->note);
    }
}
