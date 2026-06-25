<?php

declare(strict_types=1);

namespace Tests\Feature\Realtime;

use App\Modules\Channels\Models\Channel;
use App\Modules\Conversations\Events\ConversationActivity;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Repositories\Contracts\MessageRepositoryInterface;
use App\Shared\Enums\ChannelType;
use App\Shared\Enums\MessageStatus;
use App\Shared\Models\Tenant;
use App\Shared\Tenancy\TenantContext;
use App\Shared\Tenancy\TestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Живой чат: создание сообщения транслирует ConversationActivity (кабинет/виджет
 * подтягивают его без перезагрузки). Тестовая песочница — не транслируется.
 */
final class ConversationActivityTest extends TestCase
{
    use RefreshDatabase;

    private function setupConversation(ChannelType $type): Conversation
    {
        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id, 'type' => $type]);

        return Conversation::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id, 'external_chat_id' => 'sess-1']);
    }

    public function test_web_message_broadcasts_on_tenant_and_widget_channels(): void
    {
        Event::fake([ConversationActivity::class]);
        $conversation = $this->setupConversation(ChannelType::Web);

        $this->app->make(MessageRepositoryInterface::class)
            ->recordOutbound($conversation, 'ответ оператора', MessageStatus::Sent);

        Event::assertDispatched(ConversationActivity::class, function (ConversationActivity $e) use ($conversation): bool {
            return $e->conversationId === (string) $conversation->id
                && $e->tenantId === (string) $conversation->tenant_id
                && $e->widgetChannel !== null; // веб-виджет → есть публичный канал сессии
        });
    }

    public function test_messenger_message_broadcasts_only_on_tenant_channel(): void
    {
        Event::fake([ConversationActivity::class]);
        $conversation = $this->setupConversation(ChannelType::Telegram);

        $this->app->make(MessageRepositoryInterface::class)
            ->recordOutbound($conversation, 'ответ', MessageStatus::Sent);

        // Не веб-виджет → публичного канала сессии нет (только tenant.{id}).
        Event::assertDispatched(ConversationActivity::class, fn (ConversationActivity $e): bool => $e->widgetChannel === null);
    }

    public function test_sandbox_message_is_not_broadcast(): void
    {
        Event::fake([ConversationActivity::class]);
        $conversation = $this->setupConversation(ChannelType::Web);
        $messages = $this->app->make(MessageRepositoryInterface::class);

        // Тестовый прогон бота (TestContext активен) — событие не шлём.
        $this->app->make(TestContext::class)->run(function () use ($messages, $conversation): void {
            $messages->recordOutbound($conversation, 'тестовый ответ', MessageStatus::Sent);
        });

        Event::assertNotDispatched(ConversationActivity::class);
    }
}
