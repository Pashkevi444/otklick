<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Illuminate\Support\Carbon;

/**
 * Разбирает дату из свободного русского текста относительно «сегодня».
 * Возвращает дату в формате Y-m-d или null, если дату распознать не удалось.
 *
 * Поддерживает: сегодня/завтра/послезавтра, дни недели (ближайший от сегодня),
 * dd.mm[.yyyy] и dd/mm, «dd <месяц>», голое число дня («20», «20 числа»).
 * Прошедшие в этом году/месяце даты переносятся вперёд.
 */
final class RussianDateParser
{
    /** Стем дня недели → ISO-номер (Пн=1 … Вс=7). */
    private const array WEEKDAYS = [
        'понедельн' => 1,
        'вторник' => 2,
        'сред' => 3,
        'четверг' => 4,
        'пятниц' => 5,
        'суббот' => 6,
        'воскресень' => 7,
    ];

    /** Стем месяца → номер. Порядок важен: специфичные стемы раньше «ма». */
    private const array MONTHS = [
        'январ' => 1, 'феврал' => 2, 'март' => 3, 'апрел' => 4,
        'июн' => 6, 'июл' => 7, 'август' => 8, 'сентябр' => 9,
        'октябр' => 10, 'ноябр' => 11, 'декабр' => 12, 'ма' => 5,
    ];

    public static function parse(string $text, Carbon $today): ?string
    {
        $t = trim(mb_strtolower($text));
        $today = $today->copy()->startOfDay();

        if ($t === '') {
            return null;
        }

        // Явные дату/месяц («суббота 27 июня») разбираем РАНЬШE дня недели — иначе
        // «суббота» перебивала «27 июня» и возвращался ближайший, не названный день.
        return self::relative($t, $today)
            ?? self::numeric($t, $today)
            ?? self::dayWithMonth($t, $today)
            ?? self::weekday($t, $today)
            ?? self::bareDay($t, $today);
    }

    private static function relative(string $t, Carbon $today): ?string
    {
        if (str_contains($t, 'послезавтра')) {
            return $today->copy()->addDays(2)->format('Y-m-d');
        }
        if (str_contains($t, 'завтра')) {
            return $today->copy()->addDay()->format('Y-m-d');
        }
        if (str_contains($t, 'сегодня')) {
            return $today->format('Y-m-d');
        }
        // «через неделю»/«через 2 недели», «через 3 дня».
        if (str_contains($t, 'через') && str_contains($t, 'недел')) {
            $weeks = preg_match('/(\d+)\s*недел/u', $t, $m) === 1 ? max(1, (int) $m[1]) : 1;

            return $today->copy()->addDays($weeks * 7)->format('Y-m-d');
        }
        if (preg_match('/через\s+(\d+)\s*д(ень|ня|ней)/u', $t, $m) === 1) {
            return $today->copy()->addDays(max(1, (int) $m[1]))->format('Y-m-d');
        }

        return null;
    }

    private static function weekday(string $t, Carbon $today): ?string
    {
        foreach (preg_split('/[^а-яё]+/u', $t) ?: [] as $token) {
            if ($token === '') {
                continue;
            }
            foreach (self::WEEKDAYS as $stem => $iso) {
                if (str_starts_with($token, $stem)) {
                    $diff = ($iso - $today->dayOfWeekIso + 7) % 7;

                    // «следующую субботу» — на неделю вперёд от ближайшей.
                    if (str_contains($t, 'следующ')) {
                        $diff += 7;
                    }

                    return $today->copy()->addDays($diff)->format('Y-m-d');
                }
            }
        }

        return null;
    }

    private static function numeric(string $t, Carbon $today): ?string
    {
        if (preg_match('/\b(\d{1,2})[.\/](\d{1,2})(?:[.\/](\d{2,4}))?\b/u', $t, $m) !== 1) {
            return null;
        }

        $day = (int) $m[1];
        $month = (int) $m[2];
        $explicitYear = isset($m[3]);
        $year = $explicitYear ? (int) $m[3] : $today->year;
        if ($year < 100) {
            $year += 2000;
        }

        return self::build($day, $month, $year, $today, bumpYear: ! $explicitYear);
    }

    private static function dayWithMonth(string $t, Carbon $today): ?string
    {
        if (preg_match('/(\d{1,2})\s+([а-яё]+)/u', $t, $m) !== 1) {
            return null;
        }

        foreach (self::MONTHS as $stem => $month) {
            if (str_starts_with($m[2], $stem)) {
                return self::build((int) $m[1], $month, $today->year, $today, bumpYear: true);
            }
        }

        return null;
    }

    private static function bareDay(string $t, Carbon $today): ?string
    {
        // Только когда сообщение — это по сути сам номер дня («20», «на 20»,
        // «20 числа»), а НЕ время («в 15») и не фраза с днём недели («в
        // воскресенье в 15»): иначе раньше «15» ошибочно становилось 15-м числом.
        if (preg_match('/^(?:на\s+|к\s+)?(\d{1,2})(?:\s*(?:числа|число|го|е))?$/u', $t, $m) !== 1) {
            return null;
        }

        $day = (int) $m[1];
        if ($day < 1 || $day > 31) {
            return null;
        }

        $year = $today->year;
        $month = $today->month;
        if ($day < $today->day) {
            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }
        }

        return checkdate($month, $day, $year)
            ? Carbon::create($year, $month, $day, 0, 0, 0)->format('Y-m-d')
            : null;
    }

    /**
     * Собирает дату; при $bumpYear переносит прошедшую дату на следующий год.
     */
    private static function build(int $day, int $month, int $year, Carbon $today, bool $bumpYear): ?string
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        $date = Carbon::create($year, $month, $day, 0, 0, 0);

        if ($bumpYear && $date->lt($today)) {
            $date->addYear();
        }

        return $date->format('Y-m-d');
    }
}
