<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Modules\Bot\Contracts\BotApi;
use App\Modules\Channels\Contracts\ChannelGateway;
use App\Modules\Channels\Contracts\ChannelsApi;
use App\Modules\Channels\Jobs\DeliverBotReply;
use App\Modules\Channels\Models\Channel;
use App\Modules\Clients\Models\Client;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Conversations\Repositories\Contracts\ConversationRepositoryInterface;
use App\Modules\Conversations\Repositories\Contracts\MessageRepositoryInterface;
use App\Modules\Conversations\Services\ContactCapture;
use App\Modules\Conversations\Services\IncomingMessageService;
use App\Modules\Conversations\Services\SpamDetector;
use App\Modules\Knowledge\Contracts\KnowledgeApi;
use App\Modules\Knowledge\Models\KnowledgeGap;
use App\Modules\Notifications\Contracts\NotificationsApi;
use App\Shared\DTO\BotReply;
use App\Shared\DTO\IncomingMessage;
use App\Shared\Enums\ChannelType;
use App\Shared\Enums\ConversationOutcome;
use App\Shared\Enums\ConversationStatus;
use App\Shared\Enums\MessageStatus;
use App\Shared\Models\Tenant;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use RuntimeException;
use Tests\TestCase;

final class IncomingMessageServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** Контракт Notifications: notify/исходящие — no-op (нет получателей). */
    private function notifications(): NotificationsApi
    {
        $api = Mockery::mock(NotificationsApi::class);
        $api->shouldReceive('notify')->byDefault();
        $api->shouldReceive('sendOwnerNotificationAsync')->byDefault();

        return $api;
    }

    /** Контракт Channels: резолвер канала отдаёт переданный шлюз. */
    private function gateways(ChannelGateway $gateway): ChannelsApi
    {
        $api = Mockery::mock(ChannelsApi::class);
        $api->shouldReceive('for')->andReturn($gateway);

        return $api;
    }

    public function test_sends_composed_answer_and_records_messages(): void
    {
        $channel = $this->channel();
        $conversation = new Conversation;
        $conversation->id = 'conv-1';
        $incoming = new IncomingMessage('555', '42', 'есть ли доставка?', 'Иван', 'https://t.me/ivan');

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->with('ch-1', '555', 'Иван', 'https://t.me/ivan')->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once()->with($conversation);
        $conversations->shouldNotReceive('updateStatus');

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->with($conversation, $incoming)->andReturn(new Message);
        $messages->shouldReceive('recordOutbound')
            ->once()->with($conversation, 'Доставка бесплатно от 1000₽', MessageStatus::Sent)->andReturn(new Message);

        $responder = Mockery::mock(BotApi::class);
        $responder->shouldReceive('respond')
            ->once()->with(Mockery::type(Tenant::class), $conversation, 'есть ли доставка?')
            ->andReturn(new BotReply('Доставка бесплатно от 1000₽', escalate: false));

        $gateway = Mockery::mock(ChannelGateway::class);
        $gateway->shouldReceive('provider')->andReturn(ChannelType::Telegram);
        $gateway->shouldReceive('send')->once()->with($channel, '555', 'Доставка бесплатно от 1000₽', null, []);

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once()->with($conversation, 'есть ли доставка?');

        (new IncomingMessageService($conversations, $messages, $this->gateways($gateway), $responder, $contacts, Mockery::mock(KnowledgeApi::class), $this->spam(), $this->notifications()))->handle($channel, $incoming);
    }

    public function test_operator_handling_silences_bot(): void
    {
        // Диалог перехвачен оператором → бот не отвечает: responder не зовём,
        // исходящее не пишем и в канал не шлём (только фиксируем входящее).
        $channel = $this->channel();
        $conversation = new Conversation;
        $conversation->id = 'conv-1';
        $conversation->operator_active_at = now();
        $incoming = new IncomingMessage('555', '42', 'оператор тут?', 'Иван', null);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once()->with($conversation);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $messages->shouldNotReceive('recordOutbound');

        $responder = Mockery::mock(BotApi::class);
        $responder->shouldNotReceive('respond');

        $gateway = Mockery::mock(ChannelGateway::class);
        $gateway->shouldReceive('provider')->andReturn(ChannelType::Telegram);
        $gateway->shouldNotReceive('send');

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldNotReceive('fromInbound');

        (new IncomingMessageService($conversations, $messages, $this->gateways($gateway), $responder, $contacts, Mockery::mock(KnowledgeApi::class), $this->spam(), $this->notifications()))->handle($channel, $incoming);
    }

    public function test_escalated_conversation_still_answers_with_note(): void
    {
        // Диалог эскалирован (ждёт оператора), оператор НЕ перехватил → бот не
        // молчит: отвечает на вопрос, добавляя пометку, что оператор подключён.
        $channel = $this->channel();
        $conversation = new Conversation;
        $conversation->id = 'conv-1';
        $conversation->status = ConversationStatus::NeedsHuman;
        $incoming = new IncomingMessage('555', '42', 'покажи примеры работ', 'Иван', null);

        $expected = BotReply::ESCALATED_NOTE."\n\nВот примеры наших работ!";

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once()->with($conversation);
        $conversations->shouldNotReceive('updateStatus'); // уже эскалирован — повторно не трогаем

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $messages->shouldReceive('recordOutbound')->once()->with($conversation, $expected, MessageStatus::Sent)->andReturn(new Message);

        $responder = Mockery::mock(BotApi::class);
        $responder->shouldReceive('respond')->once()->with(Mockery::type(Tenant::class), $conversation, 'покажи примеры работ')
            ->andReturn(new BotReply('Вот примеры наших работ!', escalate: false, images: ['https://x/a.jpg']));

        $gateway = Mockery::mock(ChannelGateway::class);
        $gateway->shouldReceive('provider')->andReturn(ChannelType::Telegram);
        // Картинки ответа («примеры работ») НЕ теряются в эскалации — уходят в канал.
        $gateway->shouldReceive('send')->once()->with($channel, '555', $expected, null, ['https://x/a.jpg']);

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once()->with($conversation, 'покажи примеры работ');

        (new IncomingMessageService($conversations, $messages, $this->gateways($gateway), $responder, $contacts, Mockery::mock(KnowledgeApi::class), $this->spam(), $this->notifications()))->handle($channel, $incoming);
    }

    public function test_banned_client_gets_ban_notice_without_llm(): void
    {
        $channel = $this->channel();
        $channel->tenant->settings = ['profile' => ['phone' => '+7 900 000-00-00', 'email' => 'biz@x.ru']];
        $expected = $channel->tenant->banNotice();

        $conversation = new Conversation;
        $conversation->id = 'conv-1';
        $conversation->setRelation('client', new Client(['banned_at' => now()]));
        $incoming = new IncomingMessage('555', '42', 'привет', 'Иван', null);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once();

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $messages->shouldReceive('recordOutbound')->once()->with($conversation, $expected, MessageStatus::Sent)->andReturn(new Message);

        // Бот НЕ зовёт LLM забаненному — только фиксированное уведомление.
        $responder = Mockery::mock(BotApi::class);
        $responder->shouldNotReceive('respond');

        $gateway = Mockery::mock(ChannelGateway::class);
        $gateway->shouldReceive('provider')->andReturn(ChannelType::Telegram);
        $gateway->shouldReceive('send')->once()->with($channel, '555', $expected, null, []);

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once();

        (new IncomingMessageService($conversations, $messages, $this->gateways($gateway), $responder, $contacts, Mockery::mock(KnowledgeApi::class), $this->spam(), $this->notifications()))->handle($channel, $incoming);
    }

    public function test_spam_is_silently_dropped_and_marked(): void
    {
        $channel = $this->channel();
        $conversation = new Conversation;
        $conversation->id = 'conv-1';
        $incoming = new IncomingMessage('555', '42', 'купи крипту t.me/+abcdefgh', 'Иван', null);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('setOutcome')->once()->with($conversation, ConversationOutcome::Spam);
        $conversations->shouldReceive('touchLastMessage')->once();

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $messages->shouldNotReceive('recordOutbound');

        $responder = Mockery::mock(BotApi::class);
        $responder->shouldNotReceive('respond');

        $gateway = Mockery::mock(ChannelGateway::class);
        $gateway->shouldReceive('provider')->andReturn(ChannelType::Telegram);
        $gateway->shouldNotReceive('send');

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once();

        (new IncomingMessageService($conversations, $messages, $this->gateways($gateway), $responder, $contacts, Mockery::mock(KnowledgeApi::class), $this->spam(true), $this->notifications()))->handle($channel, $incoming);
    }

    public function test_failed_send_queues_retry_and_does_not_lose_reply(): void
    {
        Bus::fake([DeliverBotReply::class]);

        $channel = $this->channel();
        $conversation = new Conversation;
        $conversation->id = 'conv-1';
        $incoming = new IncomingMessage('555', '42', 'привет', null);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once();

        $sent = new Message;
        $sent->id = 'msg-1';
        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        // Отправка сорвалась → реплай фиксируется как «в очереди», а не теряется.
        $messages->shouldReceive('recordOutbound')->once()->with($conversation, 'Здравствуйте!', MessageStatus::Queued)->andReturn($sent);

        $responder = Mockery::mock(BotApi::class);
        $responder->shouldReceive('respond')->once()->andReturn(new BotReply('Здравствуйте!', escalate: false));

        $gateway = Mockery::mock(ChannelGateway::class);
        $gateway->shouldReceive('provider')->andReturn(ChannelType::Telegram);
        $gateway->shouldReceive('send')->once()->andThrow(new RuntimeException('telegram timeout'));

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once();

        (new IncomingMessageService($conversations, $messages, $this->gateways($gateway), $responder, $contacts, Mockery::mock(KnowledgeApi::class), $this->spam(), $this->notifications()))->handle($channel, $incoming);

        Bus::assertDispatched(DeliverBotReply::class, fn ($job): bool => $job->messageId === 'msg-1' && $job->text === 'Здравствуйте!');
    }

    public function test_escalation_marks_conversation_needs_human(): void
    {
        $channel = $this->channel();
        $conversation = new Conversation;
        $conversation->id = 'conv-1';
        $incoming = new IncomingMessage('555', '42', 'хочу пожаловаться', null);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once();
        $conversations->shouldReceive('updateStatus')->once()->with($conversation, ConversationStatus::NeedsHuman);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $messages->shouldReceive('recordOutbound')->once()->andReturn(new Message);

        $responder = Mockery::mock(BotApi::class);
        $responder->shouldReceive('respond')->once()->andReturn(new BotReply('Передаю администратору.', escalate: true));

        $gateway = Mockery::mock(ChannelGateway::class);
        $gateway->shouldReceive('provider')->andReturn(ChannelType::Telegram);
        $gateway->shouldReceive('send')->once();

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once();

        (new IncomingMessageService($conversations, $messages, $this->gateways($gateway), $responder, $contacts, Mockery::mock(KnowledgeApi::class), $this->spam(), $this->notifications()))->handle($channel, $incoming);
    }

    public function test_knowledge_gap_escalation_records_question(): void
    {
        $channel = $this->channel();
        $conversation = new Conversation;
        $conversation->id = 'conv-1';
        $incoming = new IncomingMessage('555', '42', 'а есть ли парковка?', null);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once();
        $conversations->shouldReceive('updateStatus')->once()->with($conversation, ConversationStatus::NeedsHuman);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $messages->shouldReceive('recordOutbound')->once()->andReturn(new Message);

        // Эскалация именно из-за пробела в базе знаний.
        $responder = Mockery::mock(BotApi::class);
        $responder->shouldReceive('respond')->once()->andReturn(new BotReply('Передаю администратору.', escalate: true, knowledgeGap: true));

        $gateway = Mockery::mock(ChannelGateway::class);
        $gateway->shouldReceive('provider')->andReturn(ChannelType::Telegram);
        $gateway->shouldReceive('send')->once();

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once();

        $gaps = Mockery::mock(KnowledgeApi::class);
        $gaps->shouldReceive('record')->once()->with('а есть ли парковка?', Mockery::any(), 'telegram')->andReturn(new KnowledgeGap);

        (new IncomingMessageService($conversations, $messages, $this->gateways($gateway), $responder, $contacts, $gaps, $this->spam(), $this->notifications()))->handle($channel, $incoming);
    }

    public function test_booking_closes_conversation(): void
    {
        $channel = $this->channel();
        $conversation = new Conversation;
        $conversation->id = 'conv-1';
        $incoming = new IncomingMessage('555', '42', 'да, записывайте', null);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once();
        $conversations->shouldReceive('markBooked')->once()->with($conversation);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $messages->shouldReceive('recordOutbound')->once()->andReturn(new Message);

        $responder = Mockery::mock(BotApi::class);
        $responder->shouldReceive('respond')->once()->andReturn(new BotReply('Записал вас!', escalate: false, booked: true));

        $gateway = Mockery::mock(ChannelGateway::class);
        $gateway->shouldReceive('provider')->andReturn(ChannelType::Telegram);
        $gateway->shouldReceive('send')->once();

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once();

        (new IncomingMessageService($conversations, $messages, $this->gateways($gateway), $responder, $contacts, Mockery::mock(KnowledgeApi::class), $this->spam(), $this->notifications()))->handle($channel, $incoming);
    }

    public function test_cancellation_marks_conversation_cancelled(): void
    {
        $channel = $this->channel();
        $conversation = new Conversation;
        $conversation->id = 'conv-1';
        $incoming = new IncomingMessage('555', '42', 'отмените мою запись', null);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once();
        $conversations->shouldReceive('markCancelled')->once()->with($conversation);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $messages->shouldReceive('recordOutbound')->once()->andReturn(new Message);

        $responder = Mockery::mock(BotApi::class);
        $responder->shouldReceive('respond')->once()->andReturn(new BotReply('Отменил запись.', escalate: false, cancelled: true));
        $responder->shouldReceive('cancelBookingInCrm')->once()->with($conversation);

        $gateway = Mockery::mock(ChannelGateway::class);
        $gateway->shouldReceive('provider')->andReturn(ChannelType::Telegram);
        $gateway->shouldReceive('send')->once();

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once();

        (new IncomingMessageService($conversations, $messages, $this->gateways($gateway), $responder, $contacts, Mockery::mock(KnowledgeApi::class), $this->spam(), $this->notifications()))->handle($channel, $incoming);
    }

    public function test_duplicate_inbound_does_nothing(): void
    {
        $channel = $this->channel();
        $conversation = new Conversation;
        $conversation->id = 'conv-1';
        $incoming = new IncomingMessage('555', '42', 'привет', null);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldNotReceive('touchLastMessage');
        $conversations->shouldNotReceive('updateStatus');

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(null);
        $messages->shouldNotReceive('recordOutbound');

        $responder = Mockery::mock(BotApi::class);
        $responder->shouldNotReceive('respond');

        $gateway = Mockery::mock(ChannelGateway::class);
        $gateway->shouldReceive('provider')->andReturn(ChannelType::Telegram);
        $gateway->shouldNotReceive('send');

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldNotReceive('fromInbound');

        (new IncomingMessageService($conversations, $messages, $this->gateways($gateway), $responder, $contacts, Mockery::mock(KnowledgeApi::class), $this->spam(), $this->notifications()))->handle($channel, $incoming);
    }

    private function spam(bool $isSpam = false): SpamDetector
    {
        $spam = Mockery::mock(SpamDetector::class);
        $spam->allows('isSpam')->andReturn($isSpam);

        return $spam;
    }

    private function channel(): Channel
    {
        $channel = new Channel;
        $channel->id = 'ch-1';
        $channel->type = ChannelType::Telegram;
        $channel->setRelation('tenant', new Tenant(['name' => 'Бизнес']));

        return $channel;
    }
}
