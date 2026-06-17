<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\BotReply;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Services\BookingFlow;
use App\Services\BotResponder;
use App\Services\ReplyComposer;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

final class BotResponderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function tenant(): Tenant
    {
        return new Tenant(['name' => 'Бизнес']);
    }

    public function test_active_booking_state_routes_to_flow(): void
    {
        $conversation = new Conversation;
        $conversation->booking_state = ['step' => 'service'];

        $booking = Mockery::mock(BookingFlow::class);
        $booking->shouldReceive('advance')->once()->with($conversation, 'хочу 1')
            ->andReturn(new BotReply('шаг записи', escalate: false));

        $composer = Mockery::mock(ReplyComposer::class);
        $composer->shouldNotReceive('compose');

        $reply = (new BotResponder($composer, $booking))->respond($this->tenant(), $conversation, 'хочу 1');

        $this->assertSame('шаг записи', $reply->text);
    }

    public function test_start_booking_signal_launches_flow(): void
    {
        $conversation = new Conversation;

        $booking = Mockery::mock(BookingFlow::class);
        $booking->shouldReceive('isAvailable')->once()->andReturnTrue();
        $booking->shouldReceive('start')->once()->with($conversation)
            ->andReturn(new BotReply('Какую услугу?', escalate: false));

        $composer = Mockery::mock(ReplyComposer::class);
        $composer->shouldReceive('compose')->once()->with(Mockery::any(), $conversation, true)
            ->andReturn(new BotReply('Секунду…', escalate: false, startBooking: true));

        $reply = (new BotResponder($composer, $booking))->respond($this->tenant(), $conversation, 'хочу записаться');

        $this->assertSame('Какую услугу?', $reply->text);
        $this->assertFalse($reply->startBooking);
    }

    public function test_start_returning_null_falls_back_to_escalation(): void
    {
        $conversation = new Conversation;

        $booking = Mockery::mock(BookingFlow::class);
        $booking->shouldReceive('isAvailable')->once()->andReturnTrue();
        $booking->shouldReceive('start')->once()->andReturnNull();

        $composer = Mockery::mock(ReplyComposer::class);
        $composer->shouldReceive('compose')->once()
            ->andReturn(new BotReply('Подбираю время…', escalate: false, startBooking: true));

        $reply = (new BotResponder($composer, $booking))->respond($this->tenant(), $conversation, 'запишите меня');

        $this->assertTrue($reply->escalate);
    }

    public function test_normal_reply_passes_through(): void
    {
        $conversation = new Conversation;

        $booking = Mockery::mock(BookingFlow::class);
        $booking->shouldReceive('isAvailable')->once()->andReturnFalse();
        $booking->shouldNotReceive('start');

        $composer = Mockery::mock(ReplyComposer::class);
        $composer->shouldReceive('compose')->once()->with(Mockery::any(), $conversation, false)
            ->andReturn(new BotReply('Работаем с 9 до 21.', escalate: false));

        $reply = (new BotResponder($composer, $booking))->respond($this->tenant(), $conversation, 'часы работы?');

        $this->assertSame('Работаем с 9 до 21.', $reply->text);
    }
}
