<?php

declare(strict_types=1);

namespace App\Shared\Enums;

use App\Shared\Enums\Contracts\HasLabel;
use Illuminate\Support\Carbon;

/**
 * Период, за который считается аналитика по лидам на дашборде.
 */
enum LeadAnalyticsPeriod: string implements HasLabel
{
    case Week = '7d';
    case Month = '30d';
    case Quarter = '90d';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Week => '7 дней',
            self::Month => '30 дней',
            self::Quarter => '90 дней',
            self::All => 'Всё время',
        };
    }

    /** Длина окна в днях; null — без ограничения («всё время»). */
    public function days(): ?int
    {
        return match ($this) {
            self::Week => 7,
            self::Month => 30,
            self::Quarter => 90,
            self::All => null,
        };
    }

    public static function fromValue(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Month;
    }

    /**
     * Текущее окно [from, to].
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(): array
    {
        $to = now();
        $days = $this->days();
        $from = $days === null ? Carbon::createFromTimestamp(0) : $to->copy()->subDays($days);

        return [$from, $to];
    }

    /**
     * Предыдущее сопоставимое окно (для расчёта динамики). null для «всё время».
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function previousRange(): ?array
    {
        $days = $this->days();

        if ($days === null) {
            return null;
        }

        $to = now()->subDays($days);
        $from = $to->copy()->subDays($days);

        return [$from, $to];
    }
}
