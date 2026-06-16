<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\Analytics\AnalyticsRange;
use App\DTO\Analytics\BreakdownSlice;
use App\DTO\Analytics\FunnelStage;
use App\DTO\Analytics\Gap;
use App\DTO\Analytics\LeadAnalytics;
use App\DTO\Analytics\MetricCard;
use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\LeadAnalyticsPeriod;
use App\Models\Conversation;
use App\Repositories\Contracts\LeadAnalyticsRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Считает аналитику по лидам за период: KPI с динамикой, ряды для графиков,
 * разбивки по каналам/статусам, воронку и «пробелы» (чего и где не хватает).
 * Вся арифметика — здесь; репозиторий только отдаёт выборку (скоуп по тенанту).
 */
final readonly class LeadAnalyticsService
{
    private const int RECENT_LIMIT = 8;

    /** contact_name, которые означают «имя ещё не получено». */
    private const array PLACEHOLDER_NAMES = ['Гость сайта', 'Гость'];

    /** @var array<string, string> */
    private const array CHANNEL_COLORS = [
        'telegram' => '#2E74B5',
        'web' => '#22b8cf',
        'whatsapp' => '#22c55e',
    ];

    /** @var array<string, string> */
    private const array STATUS_COLORS = [
        'open' => '#2E74B5',
        'needs_human' => '#f59e0b',
        'closed' => '#94a3b8',
    ];

    public function __construct(
        private LeadAnalyticsRepositoryInterface $repository,
    ) {}

    public function forPeriod(AnalyticsRange $range): LeadAnalytics
    {
        $leads = $this->repository->leadsForAnalytics($range->from, $range->to);

        $prev = $range->hasPrevious()
            ? $this->metrics($this->repository->leadsForAnalytics($range->previousFrom, $range->previousTo))
            : null;

        $now = $this->metrics($leads);

        return new LeadAnalytics(
            period: [
                'key' => $range->key,
                'label' => $range->label,
                'from' => $range->from->toDateString(),
                'to' => $range->to->toDateString(),
            ],
            periods: array_map(
                fn (LeadAnalyticsPeriod $p): array => ['key' => $p->value, 'label' => $p->label()],
                LeadAnalyticsPeriod::cases(),
            ),
            kpis: $this->kpis($now, $prev),
            daily: $this->daily($leads, $range),
            byChannel: $this->byChannel($leads),
            byStatus: $this->byStatus($leads),
            funnel: $this->funnel($now),
            hourly: $this->hourly($leads),
            weekday: $this->weekday($leads),
            gaps: $this->gaps($now),
            recent: $this->recent(),
            totals: [
                'leads' => $now['leads'],
                'booked' => $now['booked'],
                'needsHuman' => $now['needsHuman'],
                'withPhone' => $now['withPhone'],
                'engaged' => $now['engaged'],
            ],
        );
    }

    /**
     * @param  Collection<int, Conversation>  $leads
     * @return array<string, int|float>
     */
    private function metrics(Collection $leads): array
    {
        $count = $leads->count();
        $booked = $leads->filter(fn (Conversation $c): bool => $c->booked_at !== null)->count();
        $needsHuman = $leads->filter(fn (Conversation $c): bool => $c->status === ConversationStatus::NeedsHuman)->count();
        $withPhone = $leads->filter(fn (Conversation $c): bool => $this->filled($c->contact_phone))->count();
        $withName = $leads->filter(fn (Conversation $c): bool => $this->hasRealName($c))->count();
        $engaged = $leads->filter(fn (Conversation $c): bool => (int) $c->getAttribute('inbound_count') >= 2)->count();
        $clarSum = $leads->sum(fn (Conversation $c): int => (int) $c->clarification_attempts);

        return [
            'leads' => $count,
            'booked' => $booked,
            'needsHuman' => $needsHuman,
            'withPhone' => $withPhone,
            'withName' => $withName,
            'engaged' => $engaged,
            'conversionRate' => $this->rate($booked, $count),
            'contactRate' => $this->rate($withPhone, $count),
            'needsHumanRate' => $this->rate($needsHuman, $count),
            'engagementRate' => $this->rate($engaged, $count),
            'avgClarifications' => $count > 0 ? round($clarSum / $count, 2) : 0.0,
        ];
    }

    /**
     * @param  array<string, int|float>  $now
     * @param  array<string, int|float>|null  $prev
     * @return list<MetricCard>
     */
    private function kpis(array $now, ?array $prev): array
    {
        return [
            new MetricCard('leads', 'Новые лиды', $now['leads'], '',
                $this->delta($now['leads'], $prev['leads'] ?? null), true,
                'Обращений за период'),
            new MetricCard('conversion', 'Конверсия в запись', $now['conversionRate'], '%',
                $this->delta($now['conversionRate'], $prev['conversionRate'] ?? null), true,
                'Доля лидов, доведённых до записи'),
            new MetricCard('contacts', 'Собрано контактов', $now['contactRate'], '%',
                $this->delta($now['contactRate'], $prev['contactRate'] ?? null), true,
                'Доля лидов, оставивших телефон'),
            new MetricCard('engagement', 'Вовлечённость', $now['engagementRate'], '%',
                $this->delta($now['engagementRate'], $prev['engagementRate'] ?? null), true,
                'Доля лидов с реальным диалогом (≥2 сообщений)'),
            new MetricCard('needs_human', 'Ушло на человека', $now['needsHumanRate'], '%',
                $this->delta($now['needsHumanRate'], $prev['needsHumanRate'] ?? null), false,
                'Доля лидов, переданных администратору'),
            new MetricCard('clarifications', 'Ср. уточнений бота', $now['avgClarifications'], '',
                $this->delta($now['avgClarifications'], $prev['avgClarifications'] ?? null), false,
                'Сколько раз бот переспрашивал в среднем'),
        ];
    }

    /**
     * Ряд по дням (зона графика). Для «всё время» показываем последние 90 дней;
     * для произвольного окна — выбранный диапазон (с защитой от слишком длинного).
     *
     * @param  Collection<int, Conversation>  $leads
     * @return list<array{date: string, label: string, value: int}>
     */
    private function daily(Collection $leads, AnalyticsRange $range): array
    {
        $to = $range->to;
        $start = $range->key === LeadAnalyticsPeriod::All->value
            ? $to->copy()->subDays(90)->startOfDay()
            : $range->from->copy()->startOfDay();

        // Не строим больше ~366 точек, даже если выбран очень широкий диапазон.
        $cap = $to->copy()->subDays(366)->startOfDay();
        if ($start->lt($cap)) {
            $start = $cap;
        }

        $counts = $leads
            ->filter(fn (Conversation $c): bool => $c->created_at !== null && $c->created_at->gte($start))
            ->groupBy(fn (Conversation $c): string => $c->created_at->toDateString())
            ->map(fn (Collection $g): int => $g->count());

        $series = [];
        for ($day = $start->copy(); $day->lte($to); $day->addDay()) {
            $key = $day->toDateString();
            $series[] = [
                'date' => $key,
                'label' => $day->format('d.m'),
                'value' => (int) ($counts[$key] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * @param  Collection<int, Conversation>  $leads
     * @return list<BreakdownSlice>
     */
    private function byChannel(Collection $leads): array
    {
        $total = $leads->count();
        $grouped = $leads
            ->groupBy(fn (Conversation $c): string => $c->channel?->type->value ?? 'unknown')
            ->map(fn (Collection $g): int => $g->count());

        $slices = [];
        foreach (ChannelType::cases() as $type) {
            $value = (int) ($grouped[$type->value] ?? 0);
            if ($value === 0) {
                continue;
            }
            $slices[] = new BreakdownSlice(
                $type->value,
                $type->label(),
                $value,
                $this->rate($value, $total),
                self::CHANNEL_COLORS[$type->value],
            );
        }

        return $slices;
    }

    /**
     * @param  Collection<int, Conversation>  $leads
     * @return list<BreakdownSlice>
     */
    private function byStatus(Collection $leads): array
    {
        $total = $leads->count();
        $grouped = $leads
            ->groupBy(fn (Conversation $c): string => $c->status->value)
            ->map(fn (Collection $g): int => $g->count());

        $slices = [];
        foreach (ConversationStatus::cases() as $status) {
            $value = (int) ($grouped[$status->value] ?? 0);
            if ($value === 0) {
                continue;
            }
            $slices[] = new BreakdownSlice(
                $status->value,
                $status->label(),
                $value,
                $this->rate($value, $total),
                self::STATUS_COLORS[$status->value],
            );
        }

        return $slices;
    }

    /**
     * @param  array<string, int|float>  $now
     * @return list<FunnelStage>
     */
    private function funnel(array $now): array
    {
        $leads = (int) $now['leads'];

        return [
            new FunnelStage('leads', 'Обращения', $leads, 100.0),
            new FunnelStage('engaged', 'Диалог', (int) $now['engaged'], $this->rate((int) $now['engaged'], $leads)),
            new FunnelStage('contact', 'Контакт', (int) $now['withPhone'], $this->rate((int) $now['withPhone'], $leads)),
            new FunnelStage('booked', 'Запись', (int) $now['booked'], $this->rate((int) $now['booked'], $leads)),
        ];
    }

    /**
     * @param  Collection<int, Conversation>  $leads
     * @return list<array{hour: int, value: int}>
     */
    private function hourly(Collection $leads): array
    {
        $counts = $leads
            ->filter(fn (Conversation $c): bool => $c->created_at !== null)
            ->groupBy(fn (Conversation $c): int => (int) $c->created_at->format('G'))
            ->map(fn (Collection $g): int => $g->count());

        $series = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $series[] = ['hour' => $hour, 'value' => (int) ($counts[$hour] ?? 0)];
        }

        return $series;
    }

    /**
     * @param  Collection<int, Conversation>  $leads
     * @return list<array{key: string, label: string, value: int}>
     */
    private function weekday(Collection $leads): array
    {
        $labels = [1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб', 7 => 'Вс'];

        $counts = $leads
            ->filter(fn (Conversation $c): bool => $c->created_at !== null)
            ->groupBy(fn (Conversation $c): int => $c->created_at->dayOfWeekIso)
            ->map(fn (Collection $g): int => $g->count());

        $series = [];
        foreach ($labels as $iso => $label) {
            $series[] = ['key' => (string) $iso, 'label' => $label, 'value' => (int) ($counts[$iso] ?? 0)];
        }

        return $series;
    }

    /**
     * «Чего и где не хватает»: правила поверх метрик и подключённых каналов.
     *
     * @param  array<string, int|float>  $now
     * @return list<Gap>
     */
    private function gaps(array $now): array
    {
        $gaps = [];
        $leads = (int) $now['leads'];

        if ($leads === 0) {
            $gaps[] = new Gap(Gap::MEDIUM, 'Пока нет лидов',
                'За выбранный период обращений не было.',
                'Подключите каналы и добавьте виджет на сайт, чтобы получать обращения.');

            return $gaps;
        }

        if ($now['contactRate'] < 50.0) {
            $gaps[] = new Gap(Gap::HIGH, 'Мало контактов',
                'Телефон оставили только '.$this->pctText($now['contactRate']).' лидов — с остальными сложно связаться.',
                'Усильте сбор контактов: в «Профиле бизнеса» опишите, что бот должен запрашивать телефон до записи.');
        }

        if ($now['needsHumanRate'] > 25.0) {
            $gaps[] = new Gap(Gap::HIGH, 'База знаний не покрывает запросы',
                $this->pctText($now['needsHumanRate']).' лидов бот передал администратору — частые пробелы в ответах.',
                'Пополните «Базу знаний» популярными вопросами и ценами, чтобы бот отвечал сам.');
        }

        if ($now['avgClarifications'] > 1.0) {
            $gaps[] = new Gap(Gap::MEDIUM, 'Бот часто переспрашивает',
                'В среднем '.$now['avgClarifications'].' уточнения на диалог — клиентам приходится повторять.',
                'Уточните формулировки в базе знаний и добавьте популярные сценарии.');
        }

        if ($leads >= 5 && $now['conversionRate'] < 20.0) {
            $gaps[] = new Gap(Gap::MEDIUM, 'Низкая конверсия в запись',
                'До записи доходит только '.$this->pctText($now['conversionRate']).' обращений.',
                'Проверьте, что бот предлагает запись и удобные слоты; настройте автозапись в «Интеграциях».');
        }

        if ($now['engagementRate'] < 40.0) {
            $gaps[] = new Gap(Gap::LOW, 'Клиенты уходят после первого сообщения',
                'Реальный диалог завязывается лишь у '.$this->pctText($now['engagementRate']).' лидов.',
                'Сделайте первый ответ бота полезнее и предложите следующий шаг (запись, прайс, примеры работ).');
        }

        $connected = $this->repository->connectedChannelTypes();
        foreach ([ChannelType::Telegram, ChannelType::Web] as $type) {
            if (! in_array($type->value, $connected, true)) {
                $gaps[] = new Gap(Gap::LOW, 'Канал не подключён: '.$type->label(),
                    'Вы не принимаете обращения через «'.$type->label().'» — возможны упущенные лиды.',
                    'Подключите канал в разделе «Каналы»/«Виджет».');
            }
        }

        if ($gaps === []) {
            $gaps[] = new Gap(Gap::OK, 'Всё работает хорошо',
                'Ключевые показатели в норме: контакты собираются, бот справляется, лиды доходят до записи.',
                'Так держать — следите за динамикой по неделям.');
        }

        return $gaps;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recent(): array
    {
        return $this->repository->recentLeads(self::RECENT_LIMIT)
            ->map(fn (Conversation $c): array => [
                'id' => $c->id,
                'contact' => $this->hasRealName($c) ? $c->contact_name : 'Гость',
                'phone' => $c->contact_phone,
                'channel' => $c->channel?->type?->label() ?? '—',
                'status' => $c->status->value,
                'statusLabel' => $c->status->label(),
                'booked' => $c->booked_at !== null,
                'messages' => (int) $c->getAttribute('messages_count'),
                'createdAt' => $c->created_at?->format('d.m.Y H:i'),
            ])
            ->all();
    }

    private function rate(int $part, int $total): float
    {
        return $total > 0 ? round($part / $total * 100, 1) : 0.0;
    }

    private function delta(int|float $current, int|float|null $previous): ?float
    {
        if ($previous === null || (float) $previous <= 0.0) {
            return null;
        }

        return round(((float) $current - (float) $previous) / (float) $previous * 100, 1);
    }

    private function pctText(int|float $rate): string
    {
        return $rate.'%';
    }

    private function filled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    private function hasRealName(Conversation $c): bool
    {
        return $this->filled($c->contact_name) && ! in_array($c->contact_name, self::PLACEHOLDER_NAMES, true);
    }
}
