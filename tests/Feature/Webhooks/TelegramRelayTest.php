<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Modules\Channels\Jobs\ProcessTelegramUpdate;
use App\Modules\Channels\Models\Channel;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Notifications\Models\NotificationRecipient;
use App\Shared\DTO\BotReply;
use App\Shared\Enums\ConversationStatus;
use App\Shared\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class TelegramRelayTest extends TestCase
{
    use RefreshDatabase;

    private const string OPERATOR_CHAT = '555';

    private const string CLIENT_CHAT = '9001';

    /**
     * @return array{0: Tenant, 1: Channel, 2: Conversation}
     */
    private function escalated(): array
    {
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        NotificationRecipient::factory()->telegram(self::OPERATOR_CHAT)->create(['tenant_id' => $tenant->id]);

        $conversation = Conversation::factory()->withClient('Иван')->create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'external_chat_id' => self::CLIENT_CHAT,
            'status' => ConversationStatus::NeedsHuman,
        ]);

        return [$tenant, $channel, $conversation];
    }

    private function process(Tenant $tenant, Channel $channel, array $update): void
    {
        $this->app->call([new ProcessTelegramUpdate($tenant->id, $channel->id, $update), 'handle']);
    }

    private function clientMessage(string $text, int $messageId = 10): array
    {
        return ['update_id' => 100 + $messageId, 'message' => [
            'message_id' => $messageId, 'chat' => ['id' => self::CLIENT_CHAT], 'text' => $text, 'from' => ['first_name' => 'Иван'],
        ]];
    }

    private function operatorReply(string $text, int $replyTo): array
    {
        return ['update_id' => 900, 'message' => [
            'message_id' => 70, 'chat' => ['id' => self::OPERATOR_CHAT], 'text' => $text, 'reply_to_message' => ['message_id' => $replyTo],
        ]];
    }

    public function test_client_message_in_human_mode_is_forwarded_and_bot_still_answers(): void
    {
        Http::fake(['*sendMessage*' => Http::response(['result' => ['message_id' => 777]])]);
        [$tenant, $channel] = $this->escalated();

        $this->process($tenant, $channel, $this->clientMessage('у меня вопрос'));

        // Сообщение клиента ушло оператору (chat 555)…
        Http::assertSent(fn ($r): bool => str_contains($r->url(), '/sendMessage')
            && (string) $r['chat_id'] === self::OPERATOR_CHAT
            && str_contains((string) $r['text'], 'у меня вопрос'));

        // …и при этом бот НЕ молчит: дополнительно отвечает клиенту (chat 9001) с
        // пометкой, что диалог уже передан администратору.
        Http::assertSent(fn ($r): bool => str_contains($r->url(), '/sendMessage')
            && (string) $r['chat_id'] === self::CLIENT_CHAT
            && str_contains((string) $r['text'], BotReply::ESCALATED_NOTE));
    }

    public function test_operator_reply_relays_to_client(): void
    {
        Http::fake(['*sendMessage*' => Http::response(['result' => ['message_id' => 777]])]);
        [$tenant, $channel, $conversation] = $this->escalated();

        $this->process($tenant, $channel, $this->clientMessage('у меня вопрос'));      // форвард → cache 555:777
        $this->process($tenant, $channel, $this->operatorReply('Здравствуйте!', 777)); // ответ оператора

        Http::assertSent(fn ($r): bool => str_contains($r->url(), '/sendMessage')
            && (string) $r['chat_id'] === self::CLIENT_CHAT
            && str_contains((string) $r['text'], 'Здравствуйте!'));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id, 'direction' => 'outbound', 'text' => 'Здравствуйте!',
        ]);
    }

    public function test_close_command_closes_conversation(): void
    {
        Http::fake(['*sendMessage*' => Http::response(['result' => ['message_id' => 777]])]);
        [$tenant, $channel, $conversation] = $this->escalated();

        $this->process($tenant, $channel, $this->clientMessage('у меня вопрос'));
        $this->process($tenant, $channel, $this->operatorReply('/close', 777));

        $this->assertSame(ConversationStatus::Closed, $conversation->fresh()->status);
    }

    public function test_bot_command_returns_to_ai(): void
    {
        Http::fake(['*sendMessage*' => Http::response(['result' => ['message_id' => 777]])]);
        [$tenant, $channel, $conversation] = $this->escalated();

        $this->process($tenant, $channel, $this->clientMessage('у меня вопрос'));
        $this->process($tenant, $channel, $this->operatorReply('/bot', 777));

        $this->assertSame(ConversationStatus::Open, $conversation->fresh()->status);
    }
}
