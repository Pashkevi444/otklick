<?php

declare(strict_types=1);

namespace App\Shared\Enums;

use App\Shared\Enums\Contracts\HasLabel;
use Illuminate\Support\Carbon;

/**
 * Периодичность рассылки. None — разовая; остальные повторяются, планировщик
 * вычисляет следующий запуск через {@see nextRunFrom()}.
 */
enum BroadcastRecurrence: string implements HasLabel
{
    case None = 'none';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Один раз',
            self::Daily => 'Каждый день',
            self::Weekly => 'Каждую неделю',
            self::Monthly => 'Каждый месяц',
        };
    }

    /**
     * Следующий запуск после $from. null — для разовой рассылки (повтора нет).
     */
    public function nextRunFrom(Carbon $from): ?Carbon
    {
        return match ($this) {
            self::None => null,
            self::Daily => $from->copy()->addDay(),
            self::Weekly => $from->copy()->addWeek(),
            self::Monthly => $from->copy()->addMonthNoOverflow(),
        };
    }
}
