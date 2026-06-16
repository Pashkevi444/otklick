<?php

declare(strict_types=1);

namespace App\DTO\Analytics;

/**
 * Полная аналитика по лидам за период: KPI, ряды для графиков, разбивки,
 * воронка и выявленные пробелы. Готова к отдаче в Inertia (toArray).
 */
final readonly class LeadAnalytics
{
    /**
     * @param  array{key: string, label: string, from: string, to: string}  $period
     * @param  list<array{key: string, label: string}>  $periods
     * @param  list<MetricCard>  $kpis
     * @param  list<array{date: string, label: string, value: int}>  $daily
     * @param  list<BreakdownSlice>  $byChannel
     * @param  list<BreakdownSlice>  $byStatus
     * @param  list<FunnelStage>  $funnel
     * @param  list<array{hour: int, value: int}>  $hourly
     * @param  list<array{key: string, label: string, value: int}>  $weekday
     * @param  list<Gap>  $gaps
     * @param  list<array<string, mixed>>  $recent
     * @param  array<string, int>  $totals
     */
    public function __construct(
        public array $period,
        public array $periods,
        public array $kpis,
        public array $daily,
        public array $byChannel,
        public array $byStatus,
        public array $funnel,
        public array $hourly,
        public array $weekday,
        public array $gaps,
        public array $recent,
        public array $totals,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'period' => $this->period,
            'periods' => $this->periods,
            'kpis' => array_map(fn (MetricCard $m): array => $m->toArray(), $this->kpis),
            'daily' => $this->daily,
            'byChannel' => array_map(fn (BreakdownSlice $s): array => $s->toArray(), $this->byChannel),
            'byStatus' => array_map(fn (BreakdownSlice $s): array => $s->toArray(), $this->byStatus),
            'funnel' => array_map(fn (FunnelStage $s): array => $s->toArray(), $this->funnel),
            'hourly' => $this->hourly,
            'weekday' => $this->weekday,
            'gaps' => array_map(fn (Gap $g): array => $g->toArray(), $this->gaps),
            'recent' => $this->recent,
            'totals' => $this->totals,
        ];
    }
}
