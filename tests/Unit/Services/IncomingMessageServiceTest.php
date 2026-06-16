<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Channels\Contracts\MessengerGateway;
use App\DTO\BotReply;
use App\DTO\IncomingMessage;
use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\ContactCapture;
use App\Services\IncomingMessageService;
use App\Services\ReplyComposer;
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

        $composer = Mockery::mock(ReplyComposer::class);
        $composer->shouldReceive('compose')
            ->once()->with(Mockery::type(Tenant::class), $conversation)
            ->andReturn(new BotReply('Доставка бесплатно от 1000₽', escalate: false));

        $gateway = Mockery::mock(MessengerGateway::class);
        $gateway->shouldReceive('send')->once()->with($channel, '555', 'Доставка бесплатно от 1000₽');

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once()->with($conversation, 'есть ли доставка?');

        (new IncomingMessageService($conversations, $messages, $gateway, $composer, $contacts))->handle($channel, $incoming);
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

        $composer = Mockery::mock(ReplyComposer::class);
        $composer->shouldReceive('compose')->once()->andReturn(new BotReply('Передаю администратору.', escalate: true));

        $gateway = Mockery::mock(MessengerGateway::class);
        $gateway->shouldReceive('send')->once();

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once();

        (new IncomingMessageService($conversations, $messages, $gateway, $composer, $contacts))->handle($channel, $incoming);
    }

    public function test_booking_closes_conversation(): void
    {
        $channel = $this->channel();
        $conversation = new Conversation;
        $incoming = new IncomingMessage('555', '42', 'да, записывайте', null);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once();
        $conversations->shouldReceive('updateStatus')->once()->with($conversation, ConversationStatus::Closed);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $messages->shouldReceive('recordOutbound')->once()->andReturn(new Message);

        $composer = Mockery::mock(ReplyComposer::class);
        $composer->shouldReceive('compose')->once()->andReturn(new BotReply('Записал вас!', escalate: false, booked: true));

        $gateway = Mockery::mock(MessengerGateway::class);
        $gateway->shouldReceive('send')->once();

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once();

        (new IncomingMessageService($conversations, $messages, $gateway, $composer, $contacts))->handle($channel, $incoming);
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

        $composer = Mockery::mock(ReplyComposer::class);
        $composer->shouldNotReceive('compose');

        $gateway = Mockery::mock(MessengerGateway::class);
        $gateway->shouldNotReceive('send');

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldNotReceive('fromInbound');

        (new IncomingMessageService($conversations, $messages, $gateway, $composer, $contacts))->handle($channel, $incoming);
    }

    public function test_failed_send_is_recorded_as_failed(): void
    {
        $channel = $this->channel();
        $conversation = new Conversation;
        $incoming = new IncomingMessage('555', '42', 'вопрос', null);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once();
        $conversations->shouldNotReceive('updateStatus');

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $messages->shouldReceive('recordOutbound')
            ->once()->with($conversation, 'Ответ', MessageStatus::Failed)->andReturn(new Message);

        $composer = Mockery::mock(ReplyComposer::class);
        $composer->shouldReceive('compose')->once()->andReturn(new BotReply('Ответ', escalate: false));

        $gateway = Mockery::mock(MessengerGateway::class);
        $gateway->shouldReceive('send')->once()->andThrow(new RuntimeException('down'));

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once();

        (new IncomingMessageService($conversations, $messages, $gateway, $composer, $contacts))->handle($channel, $incoming);
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
