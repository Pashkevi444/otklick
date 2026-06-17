<?php

declare(strict_types=1);

namespace Tests\Unit\DTO;

use App\DTO\ReminderSettings;
use PHPUnit\Framework\TestCase;

final class ReminderSettingsTest extends TestCase
{
    public function test_sanitizes_and_sorts_offsets(): void
    {
        $s = ReminderSettings::fromArray(['enabled' => true, 'offsets' => [60, 1440, 60, 0, -5, 99999999]]);

        // дубли убраны, 0/отрицательные/за-предел отброшены, по убыванию.
        $this->assertSame([1440, 60], $s->offsetsMinutes);
        $this->assertTrue($s->isActive());
    }

    public function test_caps_to_five_offsets(): void
    {
        $s = ReminderSettings::fromArray(['enabled' => true, 'offsets' => [10, 20, 30, 40, 50, 60, 70]]);

        $this->assertCount(5, $s->offsetsMinutes);
    }

    public function test_not_active_when_disabled_or_empty(): void
    {
        $this->assertFalse(ReminderSettings::fromArray(['enabled' => false, 'offsets' => [60]])->isActive());
        $this->assertFalse(ReminderSettings::fromArray(['enabled' => true, 'offsets' => []])->isActive());
    }

    public function test_round_trips_to_array(): void
    {
        $this->assertSame(
            ['enabled' => true, 'offsets' => [120]],
            ReminderSettings::fromArray(['enabled' => true, 'offsets' => [120]])->toArray(),
        );
    }
}
