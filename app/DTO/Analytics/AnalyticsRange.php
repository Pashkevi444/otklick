<?php

declare(strict_types=1);

namespace App\DTO\Analytics;

use App\Enums\LeadAnalyticsPeriod;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Окно аналитики: либо пресет (7/30/90 дней / всё время), либо произвольный
 * диапазон дат, выбранный пользователем. Несёт границы [from, to], сопоставимое
 * предыдущее окно (для динамики) и ключ для кэша ИИ-разбора.
 */
final readonly class AnalyticsRange
{
    public const string CUSTOM = 'custom';

    public function __construct(
        public string $key,
        public string $label,
        public Carbon $from,
        public Carbon $to,
        public ?Carbon $previousFrom,
        public ?Carbon $previousTo,
    ) {}

    /**
     * Строит окно из входных параметров: при корректных from+to — произвольный
     * диапазон, иначе — пресет по `period` (по умолчанию 30 дней).
     */
    public static function resolve(?string $period, ?string $from, ?string $to): self
    {
        $parsedFrom = self::parse($from);
        $parsedTo = self::parse($to);

        if ($parsedFrom !== null && $parsedTo !== null) {
            $start = $parsedFrom->copy()->startOfDay();
            $end = $parsedTo->copy()->endOfDay();
            if ($end->lt($start)) {
                [$start, $end] = [$parsedTo->copy()->startOfDay(), $parsedFrom->copy()->endOfDay()];
            }

            $lengthDays = $start->diffInDays($end) + 1;
            $previousTo = $start->copy()->subSecond();
            $previousFrom = $start->copy()->subDays($lengthDays);

            return new self(
                self::CUSTOM,
                $start->format('d.m.Y').' — '.$end->format('d.m.Y'),
                $start,
                $end,
                $previousFrom,
                $previousTo,
            );
        }

        return self::fromPeriod(LeadAnalyticsPeriod::fromValue($period));
    }

    public static function fromPeriod(LeadAnalyticsPeriod $period): self
    {
        [$from, $to] = $period->range();
        $previous = $period->previousRange();

        return new self(
            $period->value,
            $period->label(),
            $from,
            $to,
            $previous[0] ?? null,
            $previous[1] ?? null,
        );
    }

    public function isCustom(): bool
    {
        return $this->key === self::CUSTOM;
    }

    public function hasPrevious(): bool
    {
        return $this->previousFrom !== null && $this->previousTo !== null;
    }

    /** Ключ кэша ИИ-разбора: у произвольного окна — с границами дат. */
    public function cacheKey(): string
    {
        return $this->isCustom()
            ? 'custom:'.$this->from->toDateString().':'.$this->to->toDateString()
            : $this->key;
    }

    private static function parse(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
