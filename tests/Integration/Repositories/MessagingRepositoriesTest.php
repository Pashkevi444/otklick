<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\DTO\IncomingMessage;
use App\DTO\NewChannelData;
use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MessagingRepositoriesTest extends TestCase
{
    use RefreshDatabase;

    private ChannelRepositoryInterface $channels;

    private ConversationRepositoryInterface $conversations;

    private MessageRepositoryInterface $messages;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->channels = $this->app->make(ChannelRepositoryInterface::class);
        $this->conversations = $this->app->make(ConversationRepositoryInterface::class);
        $this->messages = $this->app->make(MessageRepositoryInterface::class);

        $this->tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($this->tenant->id);
    }

    public function test_create_channel_persists_and_encrypts_credentials(): void
    {
        $channel = $this->channels->create(new NewChannelData(
            tenantId: $this->tenant->id,
            type: ChannelType::Telegram,
            externalId: '123456:bot',
            botToken: 'secret-bot-token',
            secretToken: 'webhook-secret',
        ));

        $this->assertSame($this->tenant->id, $channel->tenant_id);
        $this->assertSame(ChannelType::Telegram, $channel->fresh()->type);
        $this->assertSame('secret-bot-token', $channel->fresh()->botToken());
        $this->assertSame('webhook-secret', $channel->fresh()->secretToken());

        // Креды не лежат в БД открытым текстом.
        $raw = $this->app['db']->table('channels')->where('id', $channel->id)->value('credentials');
        $this->assertStringNotContainsString('secret-bot-token', (string) $raw);
    }

    public function test_first_or_create_for_chat_is_idempotent(): void
    {
        $channel = $this->channels->create(new NewChannelData(
            $this->tenant->id, ChannelType::Telegram, null, 'token', 'secret',
        ));

        $first = $this->conversations->firstOrCreateForChat($channel->id, '999', 'Иван');
        $second = $this->conversations->firstOrCreateForChat($channel->id, '999', 'Иван');

        $this->assertTrue($first->is($second));
        $this->assertSame(1, $channel->conversations()->count());
        $this->assertSame($this->tenant->id, $first->tenant_id);
    }

    public function test_creates_new_conversation_after_close(): void
    {
        $channel = $this->channels->create(new NewChannelData(
            $this->tenant->id, ChannelType::Telegram, null, 'token', 'secret',
        ));

        $first = $this->conversations->firstOrCreateForChat($channel->id, '555', null);
        $this->conversations->updateStatus($first, ConversationStatus::Closed);

        // Закрытый диалог не переиспользуется — новое обращение начинает свежий.
        $second = $this->conversations->firstOrCreateForChat($channel->id, '555', null);

        $this->assertFalse($first->is($second));
        $this->assertSame(ConversationStatus::Open, $second->status);
        $this->assertSame(2, $channel->conversations()->count());
    }

    public function test_returning_chat_starts_a_fresh_conversation(): void
    {
        $channel = $this->channels->create(new NewChannelData(
            $this->tenant->id, ChannelType::Telegram, null, 'token', 'secret',
        ));
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $first = $this->conversations->firstOrCreateForChat($channel->id, '777', null);
        $this->conversations->setClientId($first, $client->id);
        $this->conversations->updateStatus($first, ConversationStatus::Closed);

        // Новый диалог чата — чистый: client_id НЕ переносится из прошлого лида
        // (узнавание теперь через карточку клиента по идентичности канала —
        // см. ClientService::attachClient).
        $second = $this->conversations->firstOrCreateForChat($channel->id, '777', null);

        $this->assertFalse($first->is($second));
        $this->assertNull($second->client_id);
    }

    public function test_clear_client_links_nulls_conversations_of_client(): void
    {
        $channel = $this->channels->create(new NewChannelData(
            $this->tenant->id, ChannelType::Telegram, null, 'token', 'secret',
        ));
        $client = Client::factory()->create(['tenant_id' => $this->tenant->id]);

        $conv = $this->conversations->firstOrCreateForChat($channel->id, 'c-1', null);
        $this->conversations->setClientId($conv, $client->id);

        $this->conversations->clearClientLinks($client->id);

        $this->assertNull(Conversation::query()->find($conv->id)?->client_id);
    }

    public function test_close_stale_open_closes_only_inactive_unbooked(): void
    {
        $channel = $this->channels->create(new NewChannelData(
            $this->tenant->id, ChannelType::Telegram, null, 'token', 'secret',
        ));

        // Протух (открыт, давно без активности, без записи) — закроется.
        $stale = $this->conversations->firstOrCreateForChat($channel->id, 'stale', null);
        $stale->forceFill(['last_message_at' => now()->subHour()])->save();

        // Свежий — не трогаем.
        $fresh = $this->conversations->firstOrCreateForChat($channel->id, 'fresh', null);
        $fresh->forceFill(['last_message_at' => now()->subMinutes(5)])->save();

        // С записью — не трогаем (хотя и старый).
        $booked = $this->conversations->firstOrCreateForChat($channel->id, 'booked', null);
        $booked->forceFill(['last_message_at' => now()->subHour()])->save();
        $this->conversations->markBooked($booked);

        $closed = $this->conversations->closeStaleOpen(now()->subMinutes(30));

        $this->assertSame(1, $closed);
        $this->assertSame(ConversationStatus::Closed, $stale->fresh()->status);
        $this->assertSame(ConversationStatus::Open, $fresh->fresh()->status);
        $this->assertNull($stale->fresh()->booked_at); // потерян, не запись
    }

    public function test_booking_stays_in_work_until_visit_then_closes_as_successful(): void
    {
        $channel = $this->channels->create(new NewChannelData(
            $this->tenant->id, ChannelType::Telegram, null, 'token', 'secret',
        ));
        $conv = $this->conversations->firstOrCreateForChat($channel->id, 'booked1', null);
        $this->conversations->markBooked($conv);
        $this->conversations->setCrmRecordId($conv, 'rec-1');
        $this->conversations->setBookedFor($conv, now()->addDay()); // визит завтра

        // Запись оформлена, но сессия «в работе» (не закрыта), пока визит впереди.
        $conv->refresh();
        $this->assertSame(ConversationStatus::Open, $conv->status);
        $this->assertNotNull($conv->booked_at);

        // Время визита прошло → планировщик закрывает сессию.
        $this->conversations->setBookedFor($conv, now()->subHour());
        $closed = $this->conversations->closeCompletedBookingsForCurrentTenant(now());

        $this->assertSame(1, $closed);
        $conv->refresh();
        $this->assertSame(ConversationStatus::Closed, $conv->status);
    }

    public function test_active_bookings_for_chat_returns_only_upcoming_with_record(): void
    {
        // Схема: один активный диалог на чат (partial-unique), поэтому у чата
        // максимум одна активная запись.
        $channel = $this->channels->create(new NewChannelData(
            $this->tenant->id, ChannelType::Telegram, null, 'token', 'secret',
        ));

        $conv = $this->conversations->firstOrCreateForChat($channel->id, 'c1', null);
        $this->conversations->setCrmRecordId($conv, 'r1');
        $this->conversations->setBookedFor($conv, now()->addDay());

        // Другой чат — в выборку c1 не попадает.
        $other = $this->conversations->firstOrCreateForChat($channel->id, 'c2', null);
        $this->conversations->setCrmRecordId($other, 'r3');
        $this->conversations->setBookedFor($other, now()->addDay());

        $active = $this->conversations->activeBookingsForChat($channel->id, 'c1');
        $this->assertCount(1, $active);
        $this->assertSame('r1', $active->first()?->crm_record_id);

        // Время визита прошло — запись больше не «активна».
        $this->conversations->setBookedFor($conv, now()->subDay());
        $this->assertCount(0, $this->conversations->activeBookingsForChat($channel->id, 'c1'));
    }

    public function test_upcoming_bookings_exclude_cancelled_and_closed(): void
    {
        $channel = $this->channels->create(new NewChannelData(
            $this->tenant->id, ChannelType::Telegram, null, 'token', 'secret',
        ));
        $make = fn (array $a) => Conversation::factory()->create(array_merge(
            ['tenant_id' => $this->tenant->id, 'channel_id' => $channel->id, 'crm_record_id' => 'r', 'booked_for' => now()->addHours(2)],
            $a,
        ));

        $make(['external_chat_id' => 'a', 'status' => 'open', 'crm_record_id' => 'r1']);                       // активна — попадёт
        $make(['external_chat_id' => 'b', 'status' => 'closed', 'crm_record_id' => 'r2', 'cancelled_at' => now()]); // отменена — нет
        $make(['external_chat_id' => 'c', 'status' => 'closed', 'crm_record_id' => 'r3']);                     // закрыта админом — нет

        $upcoming = $this->conversations->upcomingBookedForCurrentTenant(now(), now()->addDay());

        $this->assertCount(1, $upcoming);
        $this->assertSame('r1', $upcoming->first()?->crm_record_id);
    }

    public function test_reconcile_does_not_close_future_or_non_crm_bookings(): void
    {
        $channel = $this->channels->create(new NewChannelData(
            $this->tenant->id, ChannelType::Telegram, null, 'token', 'secret',
        ));
        // Будущая запись — не трогаем.
        $future = $this->conversations->firstOrCreateForChat($channel->id, 'future', null);
        $this->conversations->markBooked($future);
        $this->conversations->setCrmRecordId($future, 'rec-2');
        $this->conversations->setBookedFor($future, now()->addDays(2));

        $this->assertSame(0, $this->conversations->closeCompletedBookingsForCurrentTenant(now()));
        $this->assertSame(ConversationStatus::Open, $future->fresh()->status);
    }

    public function test_record_inbound_dedupes_by_external_message_id(): void
    {
        $channel = $this->channels->create(new NewChannelData(
            $this->tenant->id, ChannelType::Telegram, null, 'token', 'secret',
        ));
        $conversation = $this->conversations->firstOrCreateForChat($channel->id, '999', null);

        $incoming = new IncomingMessage('999', '42', 'привет', 'Иван', raw: ['message_id' => 42]);

        $first = $this->messages->recordInbound($conversation, $incoming);
        $duplicate = $this->messages->recordInbound($conversation, $incoming);

        $this->assertNotNull($first);
        $this->assertSame(MessageDirection::Inbound, $first->direction);
        $this->assertNull($duplicate);
        $this->assertSame(1, $conversation->messages()->count());
    }

    public function test_record_outbound_persists_with_status(): void
    {
        $channel = $this->channels->create(new NewChannelData(
            $this->tenant->id, ChannelType::Telegram, null, 'token', 'secret',
        ));
        $conversation = $this->conversations->firstOrCreateForChat($channel->id, '999', null);

        $message = $this->messages->recordOutbound($conversation, 'ответ', MessageStatus::Sent);

        $this->assertSame(MessageDirection::Outbound, $message->direction);
        $this->assertSame(MessageStatus::Sent, $message->status);
        $this->assertSame('ответ', $message->text);
    }
}
