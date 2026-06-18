<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Crm\CrmGatewayResolver;
use App\Crm\Data\CrmService;
use App\Crm\Data\CrmStaff;
use App\Crm\Data\TimeSlot;
use App\Enums\CrmProvider;
use App\Llm\Contracts\LlmClient;
use App\Llm\FakeLlmClient;
use App\Models\Conversation;
use App\Models\CrmConnection;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\CrmConnectionRepositoryInterface;
use App\Services\BookingFlow;
use App\Support\RussianDateParser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

    private function flow(FakeCrmGateway $crm, bool $connected = true, ?LlmClient $llm = null, ?Conversation $lastBooked = null, ?Collection $activeBookings = null): BookingFlow
    {
        $connection = new CrmConnection;
        $connection->id = 'crm-1';
        $connection->provider = CrmProvider::Yclients;

        $connections = Mockery::mock(CrmConnectionRepositoryInterface::class);
        $connections->shouldReceive('activeForCurrentTenant')->andReturn($connected ? $connection : null);
        $connections->shouldReceive('find')->andReturn($connection)->byDefault();

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
        $conversations->shouldReceive('setContactName')->andReturnUsing(
            function (Conversation $c, string $name): void {
                $c->contact_name = $name;
            },
        );
        $conversations->shouldReceive('setCrmRecordId')->andReturnUsing(
            function (Conversation $c, ?string $id): void {
                $c->crm_record_id = $id;
            },
        );
        $conversations->shouldReceive('lastWithCrmRecordForChat')->andReturn($lastBooked)->byDefault();
        $conversations->shouldReceive('activeBookingsForChat')->andReturn($activeBookings ?? collect())->byDefault();
        $conversations->shouldReceive('setBookedFor');
        $conversations->shouldReceive('recordBookingValue')->andReturnUsing(
            function (Conversation $c, string $connId, ?string $sid, ?string $title, ?int $price): void {
                $c->crm_connection_id = $connId;
                $c->booked_service_id = $sid;
                $c->booked_service_title = $title;
                $c->booked_service_price = $price;
            },
        );

        return new BookingFlow($connections, new CrmGatewayResolver([$crm]), $conversations, $llm ?? new FakeLlmClient);
    }

    private function conversation(?string $phone = '+79990000000'): Conversation
    {
        $c = new Conversation;
        $c->contact_name = 'Иван';
        $c->contact_phone = $phone;

        return $c;
    }

    private function tenant(): Tenant
    {
        return new Tenant(['name' => 'Барбершоп', 'settings' => ['overrides' => ['crm' => true], 'profile' => ['phone' => '+7 383 000-00-00']]]);
    }

    private function crm(): FakeCrmGateway
    {
        $crm = new FakeCrmGateway;
        $crm->services = [new CrmService('s1', 'Маникюр', 1500), new CrmService('s2', 'Педикюр', 2000)];
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
        $this->assertNull($this->flow($this->crm(), connected: false)->start($this->tenant(), $this->conversation()));
    }

    public function test_start_returns_null_without_crm_feature(): void
    {
        // Право на CRM (YClients) отозвано → запись недоступна даже при подключении
        // (интеграция «резко отключаема» правом).
        $noCrm = new Tenant(['name' => 'X', 'settings' => ['overrides' => ['crm' => false]]]);

        $this->assertNull($this->flow($this->crm())->start($noCrm, $this->conversation()));
    }

    public function test_full_happy_path_creates_booking(): void
    {
        $crm = $this->crm();
        $flow = $this->flow($crm);
        $c = $this->conversation();

        $r = $flow->start($this->tenant(), $c);
        $this->assertStringContainsString('Маникюр', $r->text);
        $this->assertStringContainsString('Педикюр', $r->text);

        $r = $flow->advance($this->tenant(), $c, '1'); // Маникюр
        $this->assertStringContainsString('Анна', $r->text);
        $this->assertStringContainsString('Любой', $r->text);

        $r = $flow->advance($this->tenant(), $c, '2'); // первый мастер (1 = «любой»)
        $this->assertStringContainsStringIgnoringCase('день', $r->text);

        $r = $flow->advance($this->tenant(), $c, 'завтра'); // 2026-06-17, есть слоты
        $this->assertStringContainsString('10:00', $r->text);
        $this->assertStringContainsString('11:30', $r->text);

        $r = $flow->advance($this->tenant(), $c, '1'); // 10:00

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

    public function test_booking_captures_value_snapshot_for_value_report(): void
    {
        $crm = $this->crm();
        $flow = $this->flow($crm);
        $c = $this->conversation();

        $flow->start($this->tenant(), $c);
        $flow->advance($this->tenant(), $c, '1'); // Маникюр (s1, 1500 ₽)
        $flow->advance($this->tenant(), $c, '2'); // мастер
        $flow->advance($this->tenant(), $c, 'завтра');
        $flow->advance($this->tenant(), $c, '1'); // слот → запись

        // Снимок ценности: CRM-подключение + услуга и её цена на момент записи.
        $this->assertSame('crm-1', $c->crm_connection_id);
        $this->assertSame('s1', $c->booked_service_id);
        $this->assertSame('Маникюр', $c->booked_service_title);
        $this->assertSame(1500, $c->booked_service_price);
    }

    public function test_single_service_is_auto_selected(): void
    {
        $crm = $this->crm();
        $crm->services = [new CrmService('s1', 'Стрижка')];
        $c = $this->conversation();

        $r = $this->flow($crm)->start($this->tenant(), $c);

        // Сразу шаг мастера, а не выбора услуги.
        $this->assertSame('staff', $c->booking_state['step']);
        $this->assertStringContainsString('Любой', $r->text);
    }

    public function test_any_master_sets_zero_staff_id(): void
    {
        $crm = $this->crm();
        $flow = $this->flow($crm);
        $c = $this->conversation();

        $flow->start($this->tenant(), $c);
        $flow->advance($this->tenant(), $c, '1');      // услуга
        $flow->advance($this->tenant(), $c, 'любой');  // мастер: «Любой свободный»
        $flow->advance($this->tenant(), $c, 'завтра');
        $flow->advance($this->tenant(), $c, '1');

        $this->assertSame('0', $crm->createdBookings[0]->staffId);
    }

    public function test_llm_resolves_freeform_master_choice(): void
    {
        $crm = $this->crm();
        $crm->staff = [new CrmStaff('m9', 'Савелий')];

        // «давайте к савелию» не совпадает дословно — выручает ИИ (возвращает номер).
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->andReturn('2');

        $flow = $this->flow($crm, llm: $llm);
        $c = $this->conversation();

        $flow->start($this->tenant(), $c);
        $flow->advance($this->tenant(), $c, '1'); // услуга
        $flow->advance($this->tenant(), $c, 'давайте к савелию');

        $this->assertSame('date', $c->booking_state['step']);
        $this->assertSame('Савелий', $c->booking_state['staff_name']);
    }

    public function test_llm_resolves_date_typo(): void
    {
        $crm = $this->crm();

        // «завтрв» — опечатка; детерминированный парсер не справится, выручает ИИ.
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->andReturn('2026-06-17');

        $flow = $this->flow($crm, llm: $llm);
        $c = $this->conversation();

        $flow->start($this->tenant(), $c);
        $flow->advance($this->tenant(), $c, '1'); // услуга
        $flow->advance($this->tenant(), $c, '2'); // мастер
        $r = $flow->advance($this->tenant(), $c, 'завтрв'); // опечатка → ИИ → дата

        // Дату распознали и перешли к выбору времени (слоты подгрузились).
        $this->assertSame('slot', $c->booking_state['step']);
        $this->assertStringContainsString('10:00', $r->text);
    }

    public function test_many_slots_ask_for_time_instead_of_listing(): void
    {
        $crm = $this->crm();
        $crm->slots = array_map(
            fn (int $h): TimeSlot => new TimeSlot(sprintf('2026-06-17T%02d:00:00+07:00', $h)),
            range(10, 20), // 11 окон
        );
        $flow = $this->flow($crm);
        $c = $this->conversation();

        $flow->start($this->tenant(), $c);
        $flow->advance($this->tenant(), $c, '1');
        $flow->advance($this->tenant(), $c, '2');
        $r = $flow->advance($this->tenant(), $c, 'завтра');

        // Не вываливаем весь список текстом — предлагаем выбрать время.
        $this->assertStringContainsStringIgnoringCase('выберите', $r->text);
        $this->assertStringNotContainsString('12:00', $r->text); // полного перечня в тексте нет
        $this->assertNotNull($r->keyboard); // зато все окна — кликабельными кнопками
        $this->assertContains('12:00', $r->keyboard->labels());
        $this->assertSame('slot', $c->booking_state['step']);

        // Клиент называет время — сопоставляем с реальным слотом.
        $r = $flow->advance($this->tenant(), $c, 'в 14:00');
        $this->assertTrue($r->booked);
        $this->assertSame('2026-06-17T14:00:00+07:00', $crm->createdBookings[0]->start);
    }

    public function test_no_slots_asks_for_another_day(): void
    {
        $crm = $this->crm();
        $crm->slots = [];
        $flow = $this->flow($crm);
        $c = $this->conversation();

        $flow->start($this->tenant(), $c);
        $flow->advance($this->tenant(), $c, '1');
        $flow->advance($this->tenant(), $c, '2');
        $r = $flow->advance($this->tenant(), $c, 'завтра');

        $this->assertStringContainsStringIgnoringCase('нет свободного', $r->text);
        $this->assertSame('date', $c->booking_state['step']); // остаёмся на дне
        $this->assertCount(0, $crm->createdBookings);
    }

    public function test_asks_phone_when_missing_then_books(): void
    {
        $crm = $this->crm();
        $flow = $this->flow($crm);
        $c = $this->conversation(phone: null);

        $flow->start($this->tenant(), $c);
        $flow->advance($this->tenant(), $c, '1');
        $flow->advance($this->tenant(), $c, '2');
        $flow->advance($this->tenant(), $c, 'завтра');
        $r = $flow->advance($this->tenant(), $c, '1'); // слот выбран, телефона нет
        $this->assertStringContainsStringIgnoringCase('телефон', $r->text);
        $this->assertSame('contact', $c->booking_state['step']);
        $this->assertCount(0, $crm->createdBookings);

        $r = $flow->advance($this->tenant(), $c, 'мой номер +7 999 123-45-67');
        $this->assertTrue($r->booked);
        $this->assertCount(1, $crm->createdBookings);
        $this->assertSame('+79991234567', $crm->createdBookings[0]->clientPhone);
    }

    public function test_requires_name_before_booking(): void
    {
        $crm = $this->crm();
        $flow = $this->flow($crm);
        $c = $this->conversation();
        $c->contact_name = null; // имени нет, телефон есть

        $flow->start($this->tenant(), $c);
        $flow->advance($this->tenant(), $c, '1');
        $flow->advance($this->tenant(), $c, '2');
        $flow->advance($this->tenant(), $c, 'завтра');
        $r = $flow->advance($this->tenant(), $c, '1'); // слот выбран, имени нет

        $this->assertStringContainsStringIgnoringCase('зовут', $r->text);
        $this->assertSame('contact', $c->booking_state['step']);
        $this->assertCount(0, $crm->createdBookings);

        $r = $flow->advance($this->tenant(), $c, 'Павел');
        $this->assertTrue($r->booked);
        $this->assertSame('Павел', $crm->createdBookings[0]->clientName);
    }

    public function test_requires_both_name_and_phone(): void
    {
        $crm = $this->crm();
        $flow = $this->flow($crm);
        $c = $this->conversation(phone: null);
        $c->contact_name = null; // нет ни имени, ни телефона

        $flow->start($this->tenant(), $c);
        $flow->advance($this->tenant(), $c, '1');
        $flow->advance($this->tenant(), $c, '2');
        $flow->advance($this->tenant(), $c, 'завтра');
        $r = $flow->advance($this->tenant(), $c, '1');

        $this->assertStringContainsStringIgnoringCase('зовут', $r->text);
        $this->assertStringContainsStringIgnoringCase('телефон', $r->text);

        $r = $flow->advance($this->tenant(), $c, 'Павел, 8923-333-22-11');
        $this->assertTrue($r->booked);
        $this->assertSame('Павел', $crm->createdBookings[0]->clientName);
        $this->assertSame('+79233332211', $crm->createdBookings[0]->clientPhone);
    }

    public function test_booking_failure_escalates_and_clears_state(): void
    {
        $crm = $this->crm();
        $crm->failBooking = true;
        $flow = $this->flow($crm);
        $c = $this->conversation();

        $flow->start($this->tenant(), $c);
        $flow->advance($this->tenant(), $c, '1');
        $flow->advance($this->tenant(), $c, '2');
        $flow->advance($this->tenant(), $c, 'завтра');
        $r = $flow->advance($this->tenant(), $c, '1');

        $this->assertTrue($r->escalate);
        $this->assertFalse($r->booked);
        $this->assertNull($c->booking_state);
        // В статусе неудачи — телефон бизнеса для связи.
        $this->assertStringContainsString('+7 383 000-00-00', $r->text);
    }

    public function test_cancel_booking_for_conversation_cancels_in_its_crm(): void
    {
        $crm = $this->crm();
        $flow = $this->flow($crm);

        $c = new Conversation;
        $c->crm_record_id = 'rec-9';
        $c->crm_connection_id = 'crm-1';

        $flow->cancelBookingForConversation($c);

        $this->assertContains('rec-9', $crm->cancelledRecords);
        $this->assertNull($c->crm_record_id); // снят после успешной отмены
    }

    public function test_cancel_booking_for_conversation_noop_without_record(): void
    {
        $crm = $this->crm();
        $c = new Conversation; // нет crm_record_id/crm_connection_id

        $this->flow($crm)->cancelBookingForConversation($c);

        $this->assertSame([], $crm->cancelledRecords);
    }

    public function test_cancel_last_booking_cancels_in_crm(): void
    {
        $crm = $this->crm();
        $connection = new CrmConnection;
        $connection->provider = CrmProvider::Yclients;

        $booked = new Conversation;
        $booked->crm_record_id = 'rec-77';

        $connections = Mockery::mock(CrmConnectionRepositoryInterface::class);
        $connections->shouldReceive('activeForCurrentTenant')->andReturn($connection);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('lastWithCrmRecordForChat')->once()->with('ch-1', '9001')->andReturn($booked);
        $conversations->shouldReceive('setCrmRecordId')->once()->with($booked, null); // снят после отмены

        $flow = new BookingFlow($connections, new CrmGatewayResolver([$crm]), $conversations, new FakeLlmClient);

        $current = new Conversation;
        $current->channel_id = 'ch-1';
        $current->external_chat_id = '9001';

        $flow->cancelLastBooking($current);

        $this->assertSame(['rec-77'], $crm->cancelledRecords);
    }

    public function test_cancel_does_nothing_without_prior_record(): void
    {
        $crm = $this->crm();
        $flow = $this->flow($crm); // lastWithCrmRecordForChat → null по умолчанию

        $c = $this->conversation();
        $c->channel_id = 'ch-1';
        $c->external_chat_id = '9001';

        $flow->cancelLastBooking($c);

        $this->assertCount(0, $crm->cancelledRecords);
    }

    public function test_booking_choice_menu_lists_active_booking_with_action_buttons(): void
    {
        $booked = new Conversation;
        $booked->booked_service_title = 'Стрижка';
        $booked->booked_for = Carbon::parse('2026-06-20 15:00');

        $flow = $this->flow($this->crm(), activeBookings: collect([$booked]));
        $c = $this->conversation();
        $c->channel_id = 'ch-1';
        $c->external_chat_id = '9001';

        $r = $flow->bookingChoiceMenu($c);

        $this->assertNotNull($r);
        $this->assertStringContainsString('Стрижка', $r->text);
        $this->assertStringContainsString('20.06', $r->text);
        $this->assertNotNull($r->keyboard);
        $labels = $r->keyboard->labels();
        $this->assertContains('Перенести запись', $labels);
        $this->assertContains('Отменить запись', $labels);
        $this->assertContains('Новая запись', $labels);
    }

    public function test_booking_choice_menu_is_null_without_active_bookings(): void
    {
        // Нет предстоящих записей → обычная новая запись (меню не показываем).
        $this->assertNull($this->flow($this->crm())->bookingChoiceMenu($this->conversation()));
    }

    public function test_bare_hour_at_slot_step_picks_that_time_not_ordinal(): void
    {
        // Прод-баг: «14» бронировало 14-й слот, а не 14:00.
        $crm = $this->crm();
        $crm->slots = array_map(
            fn (int $h): TimeSlot => new TimeSlot(sprintf('2026-06-17T%02d:00:00+07:00', $h)),
            range(10, 18), // 9 окон → ветка диапазона
        );
        $flow = $this->flow($crm);
        $c = $this->conversation();

        $flow->start($this->tenant(), $c);
        $flow->advance($this->tenant(), $c, '1');
        $flow->advance($this->tenant(), $c, '2');
        $flow->advance($this->tenant(), $c, 'завтра');
        $r = $flow->advance($this->tenant(), $c, '14'); // = 14:00

        $this->assertTrue($r->booked);
        $this->assertSame('2026-06-17T14:00:00+07:00', $crm->createdBookings[0]->start);
    }

    public function test_reschedule_intent_is_ignored_during_active_wizard(): void
    {
        // «перенеси на 14» ВНУТРИ мастера — это ответ на шаг, а не перезапуск.
        $booked = new Conversation;
        $booked->crm_record_id = 'old';
        $flow = $this->flow($this->crm(), lastBooked: $booked);

        $c = $this->conversation();
        $c->booking_state = ['step' => 'slot', 'options' => [['id' => 's', 'title' => '14:00']]];

        $this->assertNull($flow->interceptIntent($this->tenant(), $c, 'перенеси на 14'));
    }

    public function test_confirm_step_reasks_when_phone_changed_but_not_given(): void
    {
        $crm = $this->crm();
        $crm->services = [new CrmService('s1', 'Стрижка')];
        $crm->staff = [];
        $flow = $this->flow($crm);

        $c = $this->conversation();
        $c->client_id = 'cl-1'; // вернувшийся → шаг подтверждения телефона

        $flow->start($this->tenant(), $c);
        $flow->advance($this->tenant(), $c, 'завтра');
        $flow->advance($this->tenant(), $c, '1'); // слот → подтверждение
        $r = $flow->advance($this->tenant(), $c, 'нет, поменялся'); // сменился, но номер не дал

        $this->assertFalse($r->booked); // НЕ бронируем на старый номер
        $this->assertStringContainsString('актуальный номер', $r->text);
        $this->assertCount(0, $crm->createdBookings);
    }

    public function test_service_exact_title_wins_over_substring(): void
    {
        // «Стрижка» не должна уехать в «Стрижка машинкой».
        $crm = $this->crm();
        $crm->services = [new CrmService('s1', 'Стрижка машинкой'), new CrmService('s2', 'Стрижка')];
        $crm->staff = [];
        $flow = $this->flow($crm);
        $c = $this->conversation();

        $flow->start($this->tenant(), $c); // 2 услуги → шаг выбора услуги
        $flow->advance($this->tenant(), $c, 'Стрижка');

        $this->assertSame('s2', $c->booking_state['service_id'] ?? null);
    }

    public function test_crm_error_on_start_escalates(): void
    {
        $crm = $this->crm();
        $crm->throwOnServices = true;
        $c = $this->conversation();

        $r = $this->flow($crm)->start($this->tenant(), $c);

        $this->assertNotNull($r);
        $this->assertTrue($r->escalate);
    }

    public function test_past_date_asks_for_future_instead_of_escalating(): void
    {
        // Прошедший день не должен валить запрос слотов в CRM (HTTP 422) и
        // эскалировать — бот просит назвать будущую дату.
        $crm = $this->crm();
        $flow = $this->flow($crm);
        $c = $this->conversation();

        $flow->start($this->tenant(), $c);
        $flow->advance($this->tenant(), $c, '1'); // услуга
        $flow->advance($this->tenant(), $c, '2'); // мастер
        $r = $flow->advance($this->tenant(), $c, '15.06.2026'); // вчера (сегодня 16.06.2026)

        $this->assertStringContainsString('прошедш', mb_strtolower($r->text));
        $this->assertFalse($r->escalate);
        $this->assertSame('date', $c->booking_state['step']); // остаёмся на дне
        $this->assertCount(0, $crm->createdBookings);
    }

    public function test_no_meta_intent_returns_null(): void
    {
        $flow = $this->flow($this->crm());
        $c = $this->conversation();

        // Обычные ответы шагов не должны распознаваться как «отмена/перенос».
        $this->assertNull($flow->interceptIntent($this->tenant(), $c, 'завтра в 15'));
        $this->assertNull($flow->interceptIntent($this->tenant(), $c, 'Павел, +7 999 123-45-67'));
        $this->assertNull($flow->interceptIntent($this->tenant(), $c, '1'));
    }

    public function test_cancel_intent_with_existing_booking_signals_cancelled(): void
    {
        $booked = new Conversation;
        $booked->crm_record_id = 'rec-5';

        $flow = $this->flow($this->crm(), lastBooked: $booked);
        $c = $this->conversation();
        $c->booking_state = ['step' => 'date']; // активный мастер записи

        $r = $flow->interceptIntent($this->tenant(), $c, 'отмени запись');

        $this->assertNotNull($r);
        $this->assertTrue($r->cancelled); // вызывающий слой отменит запись в CRM и закроет диалог
        $this->assertFalse($r->escalate);
        $this->assertNull($c->booking_state); // недооформленный мастер сброшен
    }

    public function test_cancel_escalates_when_crm_cancel_fails_instead_of_lying(): void
    {
        // Прод-баг: без partner-token YClients отмена падает с 401. Бот не должен
        // врать «отменил» — он передаёт диалог администратору.
        $crm = $this->crm();
        $crm->failCancel = true;

        $booked = new Conversation;
        $booked->crm_record_id = 'rec-9';

        $flow = $this->flow($crm, lastBooked: $booked);
        $c = $this->conversation();

        $r = $flow->interceptIntent($this->tenant(), $c, 'отмени запись');

        $this->assertNotNull($r);
        $this->assertFalse($r->cancelled);
        $this->assertTrue($r->escalate);
        $this->assertStringContainsString('администратор', mb_strtolower($r->text));
    }

    public function test_cancel_intent_without_booking_exits_flow_politely(): void
    {
        $flow = $this->flow($this->crm()); // прошлой записи нет
        $c = $this->conversation();
        $c->booking_state = ['step' => 'slot'];

        $r = $flow->interceptIntent($this->tenant(), $c, 'отмена');

        $this->assertNotNull($r);
        $this->assertFalse($r->cancelled); // отменять нечего — просто выходим
        $this->assertFalse($r->escalate);
        $this->assertNull($c->booking_state);
    }

    public function test_reschedule_intent_restarts_flow_and_cancels_old_booking_on_success(): void
    {
        $crm = $this->crm();
        $crm->services = [new CrmService('s1', 'Стрижка')]; // авто-выбор услуги
        $crm->staff = []; // мастеров нет → к любому, сразу шаг даты

        $booked = new Conversation;
        $booked->crm_record_id = 'old-rec';

        $flow = $this->flow($crm, lastBooked: $booked);
        $c = $this->conversation();
        $c->channel_id = 'ch-1';
        $c->external_chat_id = '9001';

        // Клиент просит перенос — мастер записи стартует заново.
        $r = $flow->interceptIntent($this->tenant(), $c, 'перенеси мою запись на другой день');
        $this->assertNotNull($r);
        $this->assertStringContainsString('перенесём', mb_strtolower($r->text));
        $this->assertSame('old-rec', $c->booking_state['supersedes_record_id']);
        $this->assertSame('date', $c->booking_state['step']);

        // Доводим новую запись до конца.
        $flow->advance($this->tenant(), $c, 'завтра');
        $r = $flow->advance($this->tenant(), $c, '1'); // слот → запись

        $this->assertTrue($r->booked);
        $this->assertCount(1, $crm->createdBookings); // создана новая
        $this->assertContains('old-rec', $crm->cancelledRecords); // прежняя отменена
    }

    public function test_date_step_offers_clickable_calendar_whose_buttons_parse(): void
    {
        $crm = $this->crm();
        $crm->services = [new CrmService('s1', 'Стрижка')];
        $crm->staff = []; // нет мастеров → сразу шаг даты
        $flow = $this->flow($crm);
        $c = $this->conversation();

        $r = $flow->start($this->tenant(), $c);

        $this->assertSame('date', $c->booking_state['step']);
        $this->assertNotNull($r->keyboard);
        $labels = $r->keyboard->labels();
        $this->assertCount(14, $labels); // горизонт календаря — 2 недели

        // Подпись кнопки распознаётся парсером даты (содержит «dd.mm»),
        // а нажатие (= отправка подписи) двигает сценарий к выбору времени.
        $this->assertNotNull(RussianDateParser::parse($labels[1], Carbon::now()));
        $r = $flow->advance($this->tenant(), $c, $labels[1]); // «нажал» завтрашний день
        $this->assertSame('slot', $c->booking_state['step']);
        $this->assertNotNull($r->keyboard); // время — тоже кликабельными кнопками
    }

    public function test_returning_client_confirms_phone_then_books(): void
    {
        $crm = $this->crm();
        $crm->services = [new CrmService('s1', 'Стрижка')];
        $crm->staff = [];
        $flow = $this->flow($crm);

        $c = $this->conversation(); // Иван, +79990000000
        $c->client_id = 'cl-1'; // узнанный вернувшийся клиент (привязан к карточке)

        $flow->start($this->tenant(), $c);
        $flow->advance($this->tenant(), $c, 'завтра');
        $r = $flow->advance($this->tenant(), $c, '1'); // слот выбран → просим подтвердить телефон

        $this->assertStringContainsString('всё ещё', $r->text);
        $this->assertStringContainsString('+79990000000', $r->text);
        $this->assertSame('confirm_contact', $c->booking_state['step']);
        $this->assertCount(0, $crm->createdBookings); // ещё не записали

        $r = $flow->advance($this->tenant(), $c, 'да, всё верно'); // подтвердил телефон
        $this->assertTrue($r->booked);
        $this->assertSame('+79990000000', $crm->createdBookings[0]->clientPhone);
    }

    public function test_returning_client_with_changed_phone_is_updated_before_booking(): void
    {
        $crm = $this->crm();
        $crm->services = [new CrmService('s1', 'Стрижка')];
        $crm->staff = [];
        $flow = $this->flow($crm);

        $c = $this->conversation();
        $c->client_id = 'cl-1';

        $flow->start($this->tenant(), $c);
        $flow->advance($this->tenant(), $c, 'завтра');
        $flow->advance($this->tenant(), $c, '1'); // просим подтвердить телефон
        $r = $flow->advance($this->tenant(), $c, 'теперь другой, +7 999 777-66-55'); // прислал новый

        $this->assertTrue($r->booked);
        $this->assertSame('+79997776655', $crm->createdBookings[0]->clientPhone); // запись на новый номер
    }

    public function test_new_client_is_not_asked_to_confirm_phone(): void
    {
        // У нового клиента (без client_id) телефон собирается по ходу — подтверждать
        // нечего, записываем сразу после ввода контактов.
        $crm = $this->crm();
        $crm->services = [new CrmService('s1', 'Стрижка')];
        $crm->staff = [];
        $flow = $this->flow($crm);

        $c = $this->conversation(phone: null); // нового клиента ещё не знаем
        $c->contact_name = null;

        $flow->start($this->tenant(), $c);
        $flow->advance($this->tenant(), $c, 'завтра');
        $flow->advance($this->tenant(), $c, '1'); // нет имени/телефона → шаг контактов
        $r = $flow->advance($this->tenant(), $c, 'Пётр, +7 999 123-45-67');

        $this->assertTrue($r->booked); // без шага подтверждения
    }

    public function test_reschedule_does_not_cancel_old_when_new_booking_fails(): void
    {
        $crm = $this->crm();
        $crm->services = [new CrmService('s1', 'Стрижка')];
        $crm->staff = [];
        $crm->failBooking = true; // новая запись не создастся

        $booked = new Conversation;
        $booked->crm_record_id = 'old-rec';

        $flow = $this->flow($crm, lastBooked: $booked);
        $c = $this->conversation();
        $c->channel_id = 'ch-1';
        $c->external_chat_id = '9001';

        $flow->interceptIntent($this->tenant(), $c, 'перезапиши меня');
        $flow->advance($this->tenant(), $c, 'завтра');
        $r = $flow->advance($this->tenant(), $c, '1');

        $this->assertTrue($r->escalate);
        $this->assertSame([], $crm->cancelledRecords); // прежняя запись цела — клиент не остался без слота
    }
}
