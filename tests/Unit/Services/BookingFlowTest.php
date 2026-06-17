<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Crm\CrmGatewayResolver;
use App\Crm\Data\CrmService;
use App\Crm\Data\CrmStaff;
use App\Crm\Data\TimeSlot;
use App\Enums\CrmProvider;
use App\Models\Conversation;
use App\Models\CrmConnection;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\CrmConnectionRepositoryInterface;
use App\Services\BookingFlow;
use Illuminate\Support\Carbon;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\Support\FakeCrmGateway;
use Tests\TestCase;

final class BookingFlowTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-16'); // вторник
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function flow(FakeCrmGateway $crm, bool $connected = true): BookingFlow
    {
        $connection = new CrmConnection;
        $connection->provider = CrmProvider::Yclients;

        $connections = Mockery::mock(CrmConnectionRepositoryInterface::class);
        $connections->shouldReceive('activeForCurrentTenant')->andReturn($connected ? $connection : null);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('setBookingState')->andReturnUsing(
            function (Conversation $c, ?array $s): void {
                $c->booking_state = $s;
            },
        );
        $conversations->shouldReceive('setContactPhone')->andReturnUsing(
            function (Conversation $c, string $phone): void {
                $c->contact_phone = $phone;
            },
        );

        return new BookingFlow($connections, new CrmGatewayResolver([$crm]), $conversations);
    }

    private function conversation(?string $phone = '+79990000000'): Conversation
    {
        $c = new Conversation;
        $c->contact_name = 'Иван';
        $c->contact_phone = $phone;

        return $c;
    }

    private function crm(): FakeCrmGateway
    {
        $crm = new FakeCrmGateway;
        $crm->services = [new CrmService('s1', 'Маникюр'), new CrmService('s2', 'Педикюр')];
        $crm->staff = [new CrmStaff('m1', 'Анна'), new CrmStaff('m2', 'Ольга')];
        $crm->slots = [new TimeSlot('2026-06-17T10:00:00+03:00'), new TimeSlot('2026-06-17T11:30:00+03:00')];

        return $crm;
    }

    public function test_is_available_reflects_active_connection(): void
    {
        $this->assertTrue($this->flow($this->crm())->isAvailable());
        $this->assertFalse($this->flow($this->crm(), connected: false)->isAvailable());
    }

    public function test_start_returns_null_without_connection(): void
    {
        $this->assertNull($this->flow($this->crm(), connected: false)->start($this->conversation()));
    }

    public function test_full_happy_path_creates_booking(): void
    {
        $crm = $this->crm();
        $flow = $this->flow($crm);
        $c = $this->conversation();

        $r = $flow->start($c);
        $this->assertStringContainsString('Маникюр', $r->text);
        $this->assertStringContainsString('Педикюр', $r->text);

        $r = $flow->advance($c, '1'); // Маникюр
        $this->assertStringContainsString('Анна', $r->text);
        $this->assertStringContainsString('Любой', $r->text);

        $r = $flow->advance($c, '2'); // первый мастер (1 = «любой»)
        $this->assertStringContainsStringIgnoringCase('день', $r->text);

        $r = $flow->advance($c, 'завтра'); // 2026-06-17, есть слоты
        $this->assertStringContainsString('10:00', $r->text);
        $this->assertStringContainsString('11:30', $r->text);

        $r = $flow->advance($c, '1'); // 10:00

        $this->assertTrue($r->booked);
        $this->assertFalse($r->escalate);
        $this->assertNull($c->booking_state); // состояние очищено
        $this->assertCount(1, $crm->createdBookings);
        $booking = $crm->createdBookings[0];
        $this->assertSame('s1', $booking->serviceId);
        $this->assertSame('m1', $booking->staffId);
        $this->assertSame('2026-06-17T10:00:00+03:00', $booking->start);
        $this->assertSame('+79990000000', $booking->clientPhone);
    }

    public function test_single_service_is_auto_selected(): void
    {
        $crm = $this->crm();
        $crm->services = [new CrmService('s1', 'Стрижка')];
        $c = $this->conversation();

        $r = $this->flow($crm)->start($c);

        // Сразу шаг мастера, а не выбора услуги.
        $this->assertSame('staff', $c->booking_state['step']);
        $this->assertStringContainsString('Любой', $r->text);
    }

    public function test_any_master_sets_zero_staff_id(): void
    {
        $crm = $this->crm();
        $flow = $this->flow($crm);
        $c = $this->conversation();

        $flow->start($c);
        $flow->advance($c, '1');      // услуга
        $flow->advance($c, 'любой');  // мастер: «Любой свободный»
        $flow->advance($c, 'завтра');
        $flow->advance($c, '1');

        $this->assertSame('0', $crm->createdBookings[0]->staffId);
    }

    public function test_no_slots_asks_for_another_day(): void
    {
        $crm = $this->crm();
        $crm->slots = [];
        $flow = $this->flow($crm);
        $c = $this->conversation();

        $flow->start($c);
        $flow->advance($c, '1');
        $flow->advance($c, '2');
        $r = $flow->advance($c, 'завтра');

        $this->assertStringContainsStringIgnoringCase('нет свободного', $r->text);
        $this->assertSame('date', $c->booking_state['step']); // остаёмся на дне
        $this->assertCount(0, $crm->createdBookings);
    }

    public function test_asks_phone_when_missing_then_books(): void
    {
        $crm = $this->crm();
        $flow = $this->flow($crm);
        $c = $this->conversation(phone: null);

        $flow->start($c);
        $flow->advance($c, '1');
        $flow->advance($c, '2');
        $flow->advance($c, 'завтра');
        $r = $flow->advance($c, '1'); // слот выбран, телефона нет
        $this->assertStringContainsStringIgnoringCase('телефон', $r->text);
        $this->assertSame('contact', $c->booking_state['step']);
        $this->assertCount(0, $crm->createdBookings);

        $r = $flow->advance($c, 'мой номер +7 999 123-45-67');
        $this->assertTrue($r->booked);
        $this->assertCount(1, $crm->createdBookings);
        $this->assertSame('+79991234567', $crm->createdBookings[0]->clientPhone);
    }

    public function test_booking_failure_escalates_and_clears_state(): void
    {
        $crm = $this->crm();
        $crm->failBooking = true;
        $flow = $this->flow($crm);
        $c = $this->conversation();

        $flow->start($c);
        $flow->advance($c, '1');
        $flow->advance($c, '2');
        $flow->advance($c, 'завтра');
        $r = $flow->advance($c, '1');

        $this->assertTrue($r->escalate);
        $this->assertFalse($r->booked);
        $this->assertNull($c->booking_state);
    }

    public function test_crm_error_on_start_escalates(): void
    {
        $crm = $this->crm();
        $crm->throwOnServices = true;
        $c = $this->conversation();

        $r = $this->flow($crm)->start($c);

        $this->assertNotNull($r);
        $this->assertTrue($r->escalate);
    }
}
