<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\Analytics\BreakdownSlice;
use App\DTO\Analytics\FunnelStage;
use App\DTO\Analytics\Gap;
use App\DTO\Analytics\MetricCard;
use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\LeadAnalyticsPeriod;
use App\Models\Channel;
use App\Models\Conversation;
use App\Repositories\Contracts\LeadAnalyticsRepositoryInterface;
use App\Services\LeadAnalyticsService;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

final class LeadAnalyticsServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function lead(
        ChannelType $channel,
        ?string $phone,
        ?string $name,
        int $inbound,
        ConversationStatus $status,
        bool $booked,
        int $clarifications = 0,
    ): Conversation {
        $c = new Conversation([
            'contact_phone' => $phone,
            'contact_name' => $name,
            'status' => $status,
            'clarification_attempts' => $clarifications,
        ]);
        $c->created_at = now()->subDays(1);
        $c->booked_at = $booked ? now() : null;
        $c->setAttribute('inbound_count', $inbound);

        $ch = new Channel;
        $ch->setAttribute('type', $channel);
        $c->setRelation('channel', $ch);

        return $c;
    }

    /**
     * @param  list<Conversation>  $leads
     * @param  list<string>  $connectedChannels
     */
    private function service(array $leads, array $connectedChannels = ['telegram', 'web']): LeadAnalyticsService
    {
        $repo = Mockery::mock(LeadAnalyticsRepositoryInterface::class);
        // Первый вызов — текущий период, второй (для динамики) — пустой прошлый.
        $repo->shouldReceive('leadsForAnalytics')->andReturn(new Collection($leads), new Collection);
        $repo->shouldReceive('connectedChannelTypes')->andReturn($connectedChannels);
        $repo->shouldReceive('recentLeads')->andReturn(new Collection);

        return new LeadAnalyticsService($repo);
    }

    private function kpi(array $kpis, string $key): MetricCard
    {
        return collect($kpis)->firstOrFail(fn (MetricCard $m): bool => $m->key === $key);
    }

    public function test_computes_core_kpis_and_funnel(): void
    {
        $leads = [
            $this->lead(ChannelType::Telegram, '+79990000001', 'Иван', 3, ConversationStatus::Closed, true, 0),
            $this->lead(ChannelType::Telegram, null, null, 1, ConversationStatus::Open, false, 2),
            $this->lead(ChannelType::Web, '+79990000003', 'Пётр', 2, ConversationStatus::NeedsHuman, false, 1),
            $this->lead(ChannelType::Web, null, null, 1, ConversationStatus::Closed, false, 0),
        ];

        $analytics = $this->service($leads)->forPeriod(LeadAnalyticsPeriod::Month);

        $this->assertSame(4, $this->kpi($analytics->kpis, 'leads')->value);
        $this->assertSame(25.0, $this->kpi($analytics->kpis, 'conversion')->value); // 1/4
        $this->assertSame(50.0, $this->kpi($analytics->kpis, 'contacts')->value);   // 2/4 phone
        $this->assertSame(50.0, $this->kpi($analytics->kpis, 'engagement')->value); // inbound>=2: 2/4
        $this->assertSame(25.0, $this->kpi($analytics->kpis, 'needs_human')->value);
        $this->assertSame(0.75, $this->kpi($analytics->kpis, 'clarifications')->value);

        // Воронка: обращения → диалог → контакт → запись.
        $booked = collect($analytics->funnel)->firstOrFail(fn (FunnelStage $s): bool => $s->key === 'booked');
        $this->assertSame(1, $booked->value);
        $this->assertSame(25.0, $booked->pct);

        // Разбивка по каналам.
        $tg = collect($analytics->byChannel)->firstOrFail(fn (BreakdownSlice $s): bool => $s->key === 'telegram');
        $this->assertSame(2, $tg->value);
        $this->assertSame(50.0, $tg->pct);
    }

    public function test_flags_contact_gap_when_few_phones(): void
    {
        $leads = [
            $this->lead(ChannelType::Telegram, null, null, 1, ConversationStatus::Open, false),
            $this->lead(ChannelType::Telegram, null, null, 1, ConversationStatus::Open, false),
        ];

        $analytics = $this->service($leads)->forPeriod(LeadAnalyticsPeriod::Month);

        $titles = array_map(fn (Gap $g): string => $g->title, $analytics->gaps);
        $this->assertContains('Мало контактов', $titles);
    }

    public function test_flags_missing_channel(): void
    {
        $leads = [
            $this->lead(ChannelType::Telegram, '+79990000001', 'Иван', 3, ConversationStatus::Closed, true),
        ];

        // Подключён только telegram — web должен попасть в пробелы.
        $analytics = $this->service($leads, ['telegram'])->forPeriod(LeadAnalyticsPeriod::Month);

        $titles = array_map(fn (Gap $g): string => $g->title, $analytics->gaps);
        $this->assertContains('Канал не подключён: Веб-виджет', $titles);
    }

    public function test_empty_period_reports_no_leads_gap(): void
    {
        $analytics = $this->service([])->forPeriod(LeadAnalyticsPeriod::Month);

        $this->assertSame(0, $this->kpi($analytics->kpis, 'leads')->value);
        $this->assertSame('Пока нет лидов', $analytics->gaps[0]->title);
        $this->assertCount(24, $analytics->hourly);
        $this->assertCount(7, $analytics->weekday);
    }
}
