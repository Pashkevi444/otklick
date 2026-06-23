<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\Analytics\AnalyticsRange;
use App\DTO\Analytics\BreakdownSlice;
use App\DTO\Analytics\FunnelStage;
use App\DTO\Analytics\Gap;
use App\DTO\Analytics\MetricCard;
use App\Enums\ChannelType;
use App\Enums\DealStageKind;
use App\Enums\LeadAnalyticsPeriod;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\DealStage;
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
        bool $escalated,
        bool $booked,
        int $clarifications = 0,
    ): Conversation {
        $c = new Conversation([
            'clarification_attempts' => $clarifications,
        ]);
        $c->created_at = now()->subDays(1);
        $c->booked_at = $booked ? now() : null;
        $c->escalated_at = $escalated ? now() : null;
        $c->setAttribute('inbound_count', $inbound);
        // Имя/телефон — атрибуты карточки клиента (лид к ней привязан).
        $c->setRelation('client', new Client(['name' => $name, 'phone' => $phone]));

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
        $repo->shouldReceive('dealsForAnalytics')->andReturn(new Collection);
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
            $this->lead(ChannelType::Telegram, '+79990000001', 'Иван', 3, false, true, 0),
            $this->lead(ChannelType::Telegram, null, null, 1, false, false, 2),
            $this->lead(ChannelType::Web, '+79990000003', 'Пётр', 2, true, false, 1),
            $this->lead(ChannelType::Web, null, null, 1, false, false, 0),
        ];

        $analytics = $this->service($leads)->forPeriod(AnalyticsRange::fromPeriod(LeadAnalyticsPeriod::Month));

        $this->assertSame(4, $this->kpi($analytics->kpis, 'leads')->value);
        $this->assertSame(25.0, $this->kpi($analytics->kpis, 'conversion')->value); // 1/4
        $this->assertSame(50.0, $this->kpi($analytics->kpis, 'contacts')->value);   // 2/4 phone
        // Пополнили базу (имя + телефон/email): Иван и Пётр → 2 (счётчик, не %).
        $this->assertSame(2, $this->kpi($analytics->kpis, 'base_grew')->value);
        $this->assertSame(50.0, $this->kpi($analytics->kpis, 'engagement')->value); // inbound>=2: 2/4
        $this->assertSame(25.0, $this->kpi($analytics->kpis, 'needs_human')->value);
        $this->assertSame(0.75, $this->kpi($analytics->kpis, 'clarifications')->value);

        // Воронка: обращения → диалог → контакт → в базе → запись.
        $inBase = collect($analytics->funnel)->firstOrFail(fn (FunnelStage $s): bool => $s->key === 'in_base');
        $this->assertSame(2, $inBase->value);
        $this->assertSame(50.0, $inBase->pct);

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
            $this->lead(ChannelType::Telegram, null, null, 1, false, false),
            $this->lead(ChannelType::Telegram, null, null, 1, false, false),
        ];

        $analytics = $this->service($leads)->forPeriod(AnalyticsRange::fromPeriod(LeadAnalyticsPeriod::Month));

        $titles = array_map(fn (Gap $g): string => $g->title, $analytics->gaps);
        $this->assertContains('Мало контактов', $titles);
    }

    public function test_flags_missing_channel(): void
    {
        $leads = [
            $this->lead(ChannelType::Telegram, '+79990000001', 'Иван', 3, false, true),
        ];

        // Подключён только telegram — web должен попасть в пробелы.
        $analytics = $this->service($leads, ['telegram'])->forPeriod(AnalyticsRange::fromPeriod(LeadAnalyticsPeriod::Month));

        $titles = array_map(fn (Gap $g): string => $g->title, $analytics->gaps);
        $this->assertContains('Канал не подключён: Веб-виджет', $titles);
    }

    public function test_daypart_coverage_and_engagement_depth(): void
    {
        $day = $this->lead(ChannelType::Telegram, '+79990000001', 'A', 1, false, false);
        $day->created_at = now()->setTime(12, 0); // рабочее время, 1 сообщение
        $night = $this->lead(ChannelType::Telegram, null, null, 5, false, false);
        $night->created_at = now()->setTime(23, 0); // нерабочее, 5 сообщений
        $early = $this->lead(ChannelType::Web, null, null, 3, false, false);
        $early->created_at = now()->setTime(6, 0); // нерабочее, 3 сообщения

        $analytics = $this->service([$day, $night, $early])->forPeriod(AnalyticsRange::fromPeriod(LeadAnalyticsPeriod::Month));

        // Покрытие 24/7: 1 в рабочее время, 2 — вне рабочих часов.
        $nightSlice = collect($analytics->byDaypart)->firstOrFail(fn (BreakdownSlice $s): bool => $s->key === 'night');
        $this->assertSame(2, $nightSlice->value);
        $this->assertSame(2, $analytics->totals['afterHours']);

        // Глубина диалога: бакеты по числу входящих сообщений.
        $byLabel = collect($analytics->engagement)->keyBy('label');
        $this->assertSame(1, $byLabel['1']['value']);
        $this->assertSame(1, $byLabel['2–3']['value']);
        $this->assertSame(1, $byLabel['4–6']['value']);
        $this->assertSame(0, $byLabel['7+']['value']);
    }

    public function test_empty_period_reports_no_leads_gap(): void
    {
        $analytics = $this->service([])->forPeriod(AnalyticsRange::fromPeriod(LeadAnalyticsPeriod::Month));

        $this->assertSame(0, $this->kpi($analytics->kpis, 'leads')->value);
        $this->assertSame('Пока нет лидов', $analytics->gaps[0]->title);
        $this->assertCount(24, $analytics->hourly);
        $this->assertCount(7, $analytics->weekday);
    }

    public function test_breaks_down_deals_by_stage(): void
    {
        $won = $this->stage('s-won', 'Выиграно', DealStageKind::Won);
        $active = $this->stage('s-act', 'В работе', DealStageKind::Active);
        $deals = new Collection([
            $this->deal($won), $this->deal($won), $this->deal($active),
        ]);

        $repo = Mockery::mock(LeadAnalyticsRepositoryInterface::class);
        $repo->shouldReceive('leadsForAnalytics')->andReturn(new Collection, new Collection);
        $repo->shouldReceive('dealsForAnalytics')->andReturn($deals);
        $repo->shouldReceive('connectedChannelTypes')->andReturn(['telegram', 'web']);
        $repo->shouldReceive('recentLeads')->andReturn(new Collection);

        $analytics = (new LeadAnalyticsService($repo))->forPeriod(AnalyticsRange::fromPeriod(LeadAnalyticsPeriod::Month));

        $this->assertSame(3, $analytics->totals['deals']);
        $wonSlice = collect($analytics->byStage)->firstOrFail(fn (BreakdownSlice $s): bool => $s->key === 's-won');
        $this->assertSame(2, $wonSlice->value);
        $this->assertSame('Выиграно', $wonSlice->label);
        // Крупная стадия — первой.
        $this->assertSame('s-won', $analytics->byStage[0]->key);
    }

    private function stage(string $id, string $name, DealStageKind $kind): DealStage
    {
        $stage = new DealStage(['name' => $name, 'kind' => $kind]);
        $stage->id = $id;

        return $stage;
    }

    private function deal(DealStage $stage): Deal
    {
        $deal = new Deal(['stage_id' => $stage->id]);
        $deal->setRelation('stage', $stage);

        return $deal;
    }
}
