<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\RussianDateParser;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

final class RussianDateParserTest extends TestCase
{
    /** Вторник. */
    private function today(): Carbon
    {
        return Carbon::parse('2026-06-16');
    }

    private function parse(string $text): ?string
    {
        return RussianDateParser::parse($text, $this->today());
    }

    public function test_relative_words(): void
    {
        $this->assertSame('2026-06-16', $this->parse('сегодня'));
        $this->assertSame('2026-06-17', $this->parse('давайте завтра'));
        $this->assertSame('2026-06-18', $this->parse('послезавтра'));
    }

    public function test_weekdays_pick_soonest_from_today(): void
    {
        // Сегодня вторник — он же ближайший «вторник».
        $this->assertSame('2026-06-16', $this->parse('во вторник'));
        // Ближайшая пятница на этой неделе.
        $this->assertSame('2026-06-19', $this->parse('в пятницу'));
        // Понедельник уже прошёл на этой неделе — берём следующий.
        $this->assertSame('2026-06-22', $this->parse('можно в понедельник'));
    }

    public function test_numeric_dates(): void
    {
        $this->assertSame('2026-06-18', $this->parse('18.06'));
        $this->assertSame('2026-06-18', $this->parse('18/06'));
        $this->assertSame('2026-06-25', $this->parse('25.06.2026'));
        // Дата уже прошла в этом году — переносим на следующий.
        $this->assertSame('2027-06-01', $this->parse('01.06'));
    }

    public function test_day_with_month_name(): void
    {
        $this->assertSame('2026-06-20', $this->parse('20 июня'));
        $this->assertSame('2026-07-05', $this->parse('5 июля'));
        $this->assertSame('2026-12-31', $this->parse('31 декабря'));
    }

    public function test_bare_day_number(): void
    {
        $this->assertSame('2026-06-20', $this->parse('20'));
        $this->assertSame('2026-06-20', $this->parse('20 числа'));
        $this->assertSame('2026-06-20', $this->parse('на 20'));
        // День уже прошёл в этом месяце — следующий месяц.
        $this->assertSame('2026-07-10', $this->parse('10'));
    }

    public function test_weekday_with_time_keeps_the_weekday_not_the_time(): void
    {
        // «в воскресенье в 15» — это воскресенье, а «15» это время, НЕ 15-е число.
        $this->assertSame('2026-06-21', $this->parse('в воскресенье в 15'));
    }

    public function test_bare_time_is_not_a_date(): void
    {
        // «в 15» — это время («в 15:00»), а не день месяца: пусть переспросят/ИИ.
        $this->assertNull($this->parse('в 15'));
        $this->assertNull($this->parse('в 11'));
    }

    public function test_returns_null_for_garbage(): void
    {
        $this->assertNull($this->parse('когда-нибудь'));
        $this->assertNull($this->parse(''));
        $this->assertNull($this->parse('99.99'));
    }
}
