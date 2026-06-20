<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\BotReply;
use App\Llm\FakeEmbedder;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\FlowRepositoryInterface;
use App\Services\BookingFlow;
use App\Services\BotResponder;
use App\Services\ContactGate;
use App\Services\FlowEngine;
use App\Services\ReplyComposer;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

final class BotResponderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function tenant(): Tenant
    {
        return new Tenant(['name' => 'Бизнес', 'settings' => ['overrides' => ['crm' => true]]]);
    }

    /** Контактная форма «пропускает» (контакты уже есть) — тестируем остальной поток. */
    private function gate(): ContactGate
    {
        $gate = Mockery::mock(ContactGate::class);
        $gate->shouldReceive('handle')->andReturnNull();

        return $gate;
    }

    /** Воронок нет — движок сценариев «пропускает» (handle → null). */
    private function flows(): FlowEngine
    {
        $repo = Mockery::mock(FlowRepositoryInterface::class);
        $repo->shouldReceive('activeForCurrentTenant')->andReturn(new Collection);

        return new FlowEngine($repo, Mockery::mock(ConversationRepositoryInterface::class), Mockery::mock(BookingFlow::class), new FakeEmbedder);
    }

    public function test_active_booking_state_routes_to_flow(): void
    {
        $conversation = new Conversation;
        $conversation->booking_state = ['step' => 'service'];

        $booking = Mockery::mock(BookingFlow::class);
        $booking->shouldReceive('interceptIntent')->andReturnNull(); // нет мета-намерения (отмена/перенос)
        $booking->shouldReceive('bookingChoiceMenu')->andReturnNull()->byDefault(); // нет активной записи → обычный поток
        $booking->shouldReceive('advance')->once()->with(Mockery::type(Tenant::class), $conversation, 'хочу 1')
            ->andReturn(new BotReply('шаг записи', escalate: false));

        $composer = Mockery::mock(ReplyComposer::class);
        $composer->shouldNotReceive('compose');

        $reply = (new BotResponder($composer, $booking, $this->gate(), $this->flows()))->respond($this->tenant(), $conversation, 'хочу 1');

        $this->assertSame('шаг записи', $reply->text);
    }

    public function test_start_booking_signal_launches_flow(): void
    {
        $conversation = new Conversation;

        $booking = Mockery::mock(BookingFlow::class);
        $booking->shouldReceive('interceptIntent')->andReturnNull(); // нет мета-намерения (отмена/перенос)
        $booking->shouldReceive('bookingChoiceMenu')->andReturnNull()->byDefault(); // нет активной записи → обычный поток
        $booking->shouldReceive('isAvailable')->once()->andReturnTrue();
        $booking->shouldReceive('start')->once()->with(Mockery::type(Tenant::class), $conversation)
            ->andReturn(new BotReply('Какую услугу?', escalate: false));

        $composer = Mockery::mock(ReplyComposer::class);
        $composer->shouldReceive('compose')->once()->with(Mockery::any(), $conversation, true)
            ->andReturn(new BotReply('Секунду…', escalate: false, startBooking: true));

        $reply = (new BotResponder($composer, $booking, $this->gate(), $this->flows()))->respond($this->tenant(), $conversation, 'хочу записаться');

        $this->assertSame('Какую услугу?', $reply->text);
        $this->assertFalse($reply->startBooking);
    }

    public function test_start_returning_null_falls_back_to_escalation(): void
    {
        $conversation = new Conversation;

        $booking = Mockery::mock(BookingFlow::class);
        $booking->shouldReceive('interceptIntent')->andReturnNull(); // нет мета-намерения (отмена/перенос)
        $booking->shouldReceive('bookingChoiceMenu')->andReturnNull()->byDefault(); // нет активной записи → обычный поток
        $booking->shouldReceive('isAvailable')->once()->andReturnTrue();
        $booking->shouldReceive('start')->once()->andReturnNull();

        $composer = Mockery::mock(ReplyComposer::class);
        $composer->shouldReceive('compose')->once()
            ->andReturn(new BotReply('Подбираю время…', escalate: false, startBooking: true));

        $reply = (new BotResponder($composer, $booking, $this->gate(), $this->flows()))->respond($this->tenant(), $conversation, 'запишите меня');

        $this->assertTrue($reply->escalate);
    }

    public function test_existing_booking_offers_choice_menu_instead_of_second_booking(): void
    {
        $conversation = new Conversation;
        $menu = new BotReply('У вас уже есть запись: Стрижка — 20.06 в 15:00…', escalate: false);

        $booking = Mockery::mock(BookingFlow::class);
        $booking->shouldReceive('interceptIntent')->andReturnNull();
        $booking->shouldReceive('isAvailable')->andReturnTrue();
        // Есть активная запись → меню выбора, а не молча вторая запись.
        $booking->shouldReceive('bookingChoiceMenu')->once()->with($conversation)->andReturn($menu);
        $booking->shouldNotReceive('start');

        $composer = Mockery::mock(ReplyComposer::class);
        $composer->shouldReceive('compose')->once()->andReturn(new BotReply('Секунду…', escalate: false, startBooking: true));

        $reply = (new BotResponder($composer, $booking, $this->gate(), $this->flows()))->respond($this->tenant(), $conversation, 'хочу записаться');

        $this->assertStringContainsString('уже есть запись', $reply->text);
    }

    public function test_new_booking_choice_starts_fresh_wizard_bypassing_llm(): void
    {
        $conversation = new Conversation;

        $booking = Mockery::mock(BookingFlow::class);
        $booking->shouldReceive('interceptIntent')->andReturnNull();
        $booking->shouldReceive('start')->once()->andReturn(new BotReply('Какую услугу?', escalate: false));
        $booking->shouldNotReceive('bookingChoiceMenu');

        $composer = Mockery::mock(ReplyComposer::class);
        $composer->shouldNotReceive('compose'); // «Новая запись» минует LLM и меню

        $reply = (new BotResponder($composer, $booking, $this->gate(), $this->flows()))->respond($this->tenant(), $conversation, 'Новая запись');

        $this->assertSame('Какую услугу?', $reply->text);
    }

    public function test_normal_reply_passes_through(): void
    {
        $conversation = new Conversation;

        $booking = Mockery::mock(BookingFlow::class);
        $booking->shouldReceive('interceptIntent')->andReturnNull(); // нет мета-намерения (отмена/перенос)
        $booking->shouldReceive('bookingChoiceMenu')->andReturnNull()->byDefault(); // нет активной записи → обычный поток
        $booking->shouldReceive('isAvailable')->once()->andReturnFalse();
        $booking->shouldNotReceive('start');

        $composer = Mockery::mock(ReplyComposer::class);
        $composer->shouldReceive('compose')->once()->with(Mockery::any(), $conversation, false)
            ->andReturn(new BotReply('Работаем с 9 до 21.', escalate: false));

        $reply = (new BotResponder($composer, $booking, $this->gate(), $this->flows()))->respond($this->tenant(), $conversation, 'часы работы?');

        $this->assertSame('Работаем с 9 до 21.', $reply->text);
    }
}
