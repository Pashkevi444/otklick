<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\DTO\IncomingMessage;
use App\DTO\NewChannelData;
use App\Enums\ChannelType;
use App\Enums\MessageDirection;
use App\Enums\MessageStatus;
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
