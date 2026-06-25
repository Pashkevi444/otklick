<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\WidgetRealtimeChannel;
use Tests\TestCase;

final class WidgetRealtimeChannelTest extends TestCase
{
    public function test_name_is_deterministic_and_prefixed(): void
    {
        $a = WidgetRealtimeChannel::name('chan-1', 'sess-1');
        $b = WidgetRealtimeChannel::name('chan-1', 'sess-1');

        $this->assertSame($a, $b);
        $this->assertStringStartsWith('widget.', $a);
    }

    public function test_different_sessions_get_different_channels(): void
    {
        // Изоляция: чужую сессию по имени канала не перебрать.
        $this->assertNotSame(
            WidgetRealtimeChannel::name('chan-1', 'sess-1'),
            WidgetRealtimeChannel::name('chan-1', 'sess-2'),
        );
        $this->assertNotSame(
            WidgetRealtimeChannel::name('chan-1', 'sess-1'),
            WidgetRealtimeChannel::name('chan-2', 'sess-1'),
        );
    }
}
