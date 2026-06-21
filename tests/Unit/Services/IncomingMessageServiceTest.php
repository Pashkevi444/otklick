<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Channels\ChannelGatewayResolver;
use App\Channels\Contracts\ChannelGateway;
use App\DTO\BotReply;
use App\DTO\IncomingMessage;
use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use App\Jobs\DeliverBotReply;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\KnowledgeGapRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\BotResponder;
use App\Services\ContactCapture;
use App\Services\IncomingMessageService;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use RuntimeException;
use Tests\TestCase;

final class IncomingMessageServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_sends_composed_answer_and_records_messages(): void
    {
        $channel = $this->channel();
        $conversation = new Conversation;
        $incoming = new IncomingMessage('555', '42', 'есть ли доставка?', 'Иван', 'https://t.me/ivan');

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->with('ch-1', '555', 'Иван', 'https://t.me/ivan')->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once()->with($conversation);
        $conversations->shouldNotReceive('updateStatus');

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->with($conversation, $incoming)->andReturn(new Message);
        $messages->shouldReceive('recordOutbound')
            ->once()->with($conversation, 'Доставка бесплатно от 1000₽', MessageStatus::Sent)->andReturn(new Message);

        $responder = Mockery::mock(BotResponder::class);
        $responder->shouldReceive('respond')
            ->once()->with(Mockery::type(Tenant::class), $conversation, 'есть ли доставка?')
            ->andReturn(new BotReply('Доставка бесплатно от 1000₽', escalate: false));

        $gateway = Mockery::mock(ChannelGateway::class);
        $gateway->shouldReceive('provider')->andReturn(ChannelType::Telegram);
        $gateway->shouldReceive('send')->once()->with($channel, '555', 'Доставка бесплатно от 1000₽', null, []);

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once()->with($conversation, 'есть ли доставка?');

        (new IncomingMessageService($conversations, $messages, new ChannelGatewayResolver([$gateway]), $responder, $contacts, Mockery::mock(KnowledgeGapRepositoryInterface::class)))->handle($channel, $incoming);
    }

    public function test_operator_handling_silences_bot(): void
    {
        // Диалог перехвачен оператором → бот не отвечает: responder не зовём,
        // исходящее не пишем и в канал не шлём (только фиксируем входящее).
        $channel = $this->channel();
        $conversation = new Conversation;
        $conversation->operator_active_at = now();
        $incoming = new IncomingMessage('555', '42', 'оператор тут?', 'Иван', null);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once()->with($conversation);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $messages->shouldNotReceive('recordOutbound');

        $responder = Mockery::mock(BotResponder::class);
        $responder->shouldNotReceive('respond');

        $gateway = Mockery::mock(ChannelGateway::class);
        $gateway->shouldReceive('provider')->andReturn(ChannelType::Telegram);
        $gateway->shouldNotReceive('send');

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldNotReceive('fromInbound');

        (new IncomingMessageService($conversations, $messages, new ChannelGatewayResolver([$gateway]), $responder, $contacts, Mockery::mock(KnowledgeGapRepositoryInterface::class)))->handle($channel, $incoming);
    }

    public function test_failed_send_queues_retry_and_does_not_lose_reply(): void
    {
        Bus::fake([DeliverBotReply::class]);

        $channel = $this->channel();
        $conversation = new Conversation;
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

        $responder = Mockery::mock(BotResponder::class);
        $responder->shouldReceive('respond')->once()->andReturn(new BotReply('Здравствуйте!', escalate: false));

        $gateway = Mockery::mock(ChannelGateway::class);
        $gateway->shouldReceive('provider')->andReturn(ChannelType::Telegram);
        $gateway->shouldReceive('send')->once()->andThrow(new RuntimeException('telegram timeout'));

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once();

        (new IncomingMessageService($conversations, $messages, new ChannelGatewayResolver([$gateway]), $responder, $contacts, Mockery::mock(KnowledgeGapRepositoryInterface::class)))->handle($channel, $incoming);

        Bus::assertDispatched(DeliverBotReply::class, fn ($job): bool => $job->messageId === 'msg-1' && $job->text === 'Здравствуйте!');
    }

    public function test_escalation_marks_conversation_needs_human(): void
    {
        $channel = $this->channel();
        $conversation = new Conversation;
        $incoming = new IncomingMessage('555', '42', 'хочу пожаловаться', null);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once();
        $conversations->shouldReceive('updateStatus')->once()->with($conversation, ConversationStatus::NeedsHuman);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $messages->shouldReceive('recordOutbound')->once()->andReturn(new Message);

        $responder = Mockery::mock(BotResponder::class);
        $responder->shouldReceive('respond')->once()->andReturn(new BotReply('Передаю администратору.', escalate: true));

        $gateway = Mockery::mock(ChannelGateway::class);
        $gateway->shouldReceive('provider')->andReturn(ChannelType::Telegram);
        $gateway->shouldReceive('send')->once();

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once();

        (new IncomingMessageService($conversations, $messages, new ChannelGatewayResolver([$gateway]), $responder, $contacts, Mockery::mock(KnowledgeGapRepositoryInterface::class)))->handle($channel, $incoming);
    }

    public function test_knowledge_gap_escalation_records_question(): void
    {
        $channel = $this->channel();
        $conversation = new Conversation;
        $incoming = new IncomingMessage('555', '42', 'а есть ли парковка?', null);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once();
        $conversations->shouldReceive('updateStatus')->once()->with($conversation, ConversationStatus::NeedsHuman);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $messages->shouldReceive('recordOutbound')->once()->andReturn(new Message);

        // Эскалация именно из-за пробела в базе знаний.
        $responder = Mockery::mock(BotResponder::class);
        $responder->shouldReceive('respond')->once()->andReturn(new BotReply('Передаю администратору.', escalate: true, knowledgeGap: true));

        $gateway = Mockery::mock(ChannelGateway::class);
        $gateway->shouldReceive('provider')->andReturn(ChannelType::Telegram);
        $gateway->shouldReceive('send')->once();

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once();

        $gaps = Mockery::mock(KnowledgeGapRepositoryInterface::class);
        $gaps->shouldReceive('record')->once()->with('а есть ли парковка?', Mockery::any(), 'telegram');

        (new IncomingMessageService($conversations, $messages, new ChannelGatewayResolver([$gateway]), $responder, $contacts, $gaps))->handle($channel, $incoming);
    }

    public function test_booking_closes_conversation(): void
    {
        $channel = $this->channel();
        $conversation = new Conversation;
        $incoming = new IncomingMessage('555', '42', 'да, записывайте', null);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once();
        $conversations->shouldReceive('markBooked')->once()->with($conversation);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $messages->shouldReceive('recordOutbound')->once()->andReturn(new Message);

        $responder = Mockery::mock(BotResponder::class);
        $responder->shouldReceive('respond')->once()->andReturn(new BotReply('Записал вас!', escalate: false, booked: true));

        $gateway = Mockery::mock(ChannelGateway::class);
        $gateway->shouldReceive('provider')->andReturn(ChannelType::Telegram);
        $gateway->shouldReceive('send')->once();

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once();

        (new IncomingMessageService($conversations, $messages, new ChannelGatewayResolver([$gateway]), $responder, $contacts, Mockery::mock(KnowledgeGapRepositoryInterface::class)))->handle($channel, $incoming);
    }

    public function test_cancellation_marks_conversation_cancelled(): void
    {
        $channel = $this->channel();
        $conversation = new Conversation;
        $incoming = new IncomingMessage('555', '42', 'отмените мою запись', null);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once();
        $conversations->shouldReceive('markCancelled')->once()->with($conversation);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $messages->shouldReceive('recordOutbound')->once()->andReturn(new Message);

        $responder = Mockery::mock(BotResponder::class);
        $responder->shouldReceive('respond')->once()->andReturn(new BotReply('Отменил запись.', escalate: false, cancelled: true));
        $responder->shouldReceive('cancelBookingInCrm')->once()->with($conversation);

        $gateway = Mockery::mock(ChannelGateway::class);
        $gateway->shouldReceive('provider')->andReturn(ChannelType::Telegram);
        $gateway->shouldReceive('send')->once();

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once();

        (new IncomingMessageService($conversations, $messages, new ChannelGatewayResolver([$gateway]), $responder, $contacts, Mockery::mock(KnowledgeGapRepositoryInterface::class)))->handle($channel, $incoming);
    }

    public function test_duplicate_inbound_does_nothing(): void
    {
        $channel = $this->channel();
        $conversation = new Conversation;
        $incoming = new IncomingMessage('555', '42', 'привет', null);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldNotReceive('touchLastMessage');
        $conversations->shouldNotReceive('updateStatus');

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(null);
        $messages->shouldNotReceive('recordOutbound');

        $responder = Mockery::mock(BotResponder::class);
        $responder->shouldNotReceive('respond');

        $gateway = Mockery::mock(ChannelGateway::class);
        $gateway->shouldReceive('provider')->andReturn(ChannelType::Telegram);
        $gateway->shouldNotReceive('send');

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldNotReceive('fromInbound');

        (new IncomingMessageService($conversations, $messages, new ChannelGatewayResolver([$gateway]), $responder, $contacts, Mockery::mock(KnowledgeGapRepositoryInterface::class)))->handle($channel, $incoming);
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
