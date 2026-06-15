<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Channels\Contracts\MessengerGateway;
use App\DTO\IncomingMessage;
use App\Enums\ChannelType;
use App\Enums\MessageStatus;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\IncomingMessageService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use RuntimeException;
use Tests\TestCase;

final class IncomingMessageServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_replies_with_echo_and_records_both_messages(): void
    {
        $channel = $this->channel();
        $conversation = new Conversation;
        $incoming = new IncomingMessage('555', '42', 'привет', 'Иван');

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')
            ->once()->with('ch-1', '555', 'Иван')->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once()->with($conversation);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->with($conversation, $incoming)->andReturn(new Message);
        $messages->shouldReceive('recordOutbound')
            ->once()->with($conversation, 'Вы написали: привет', MessageStatus::Sent)->andReturn(new Message);

        $gateway = Mockery::mock(MessengerGateway::class);
        $gateway->shouldReceive('send')->once()->with($channel, '555', 'Вы написали: привет');

        (new IncomingMessageService($conversations, $messages, $gateway))->handle($channel, $incoming);
    }

    public function test_duplicate_inbound_does_not_reply_or_record_outbound(): void
    {
        $channel = $this->channel();
        $conversation = new Conversation;
        $incoming = new IncomingMessage('555', '42', 'привет', 'Иван');

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldNotReceive('touchLastMessage');

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(null);
        $messages->shouldNotReceive('recordOutbound');

        $gateway = Mockery::mock(MessengerGateway::class);
        $gateway->shouldNotReceive('send');

        (new IncomingMessageService($conversations, $messages, $gateway))->handle($channel, $incoming);
    }

    public function test_failed_send_is_recorded_as_failed_without_throwing(): void
    {
        $channel = $this->channel();
        $conversation = new Conversation;
        $incoming = new IncomingMessage('555', '42', 'привет', null);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once();

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $messages->shouldReceive('recordOutbound')
            ->once()->with($conversation, 'Вы написали: привет', MessageStatus::Failed)->andReturn(new Message);

        $gateway = Mockery::mock(MessengerGateway::class);
        $gateway->shouldReceive('send')->once()->andThrow(new RuntimeException('Telegram down'));

        (new IncomingMessageService($conversations, $messages, $gateway))->handle($channel, $incoming);
    }

    private function channel(): Channel
    {
        $channel = new Channel;
        $channel->id = 'ch-1';
        $channel->type = ChannelType::Telegram;

        return $channel;
    }
}
