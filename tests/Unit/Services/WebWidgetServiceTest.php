<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTO\BotReply;
use App\Enums\MessageStatus;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\BotResponder;
use App\Services\ContactCapture;
use App\Services\WebWidgetService;
use Illuminate\Support\Facades\Crypt;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

final class WebWidgetServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_booking_closes_conversation(): void
    {
        $channel = new Channel;
        $channel->id = 'web-1';
        $channel->setRelation('tenant', new Tenant(['name' => 'Бизнес']));

        $token = Crypt::encryptString('web-1|sess-1');
        $conversation = new Conversation;

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')
            ->once()->with('web-1', 'sess-1', null, null)->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once()->with($conversation);
        $conversations->shouldReceive('markBooked')->once()->with($conversation);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn(new Message);
        $messages->shouldReceive('recordOutbound')
            ->once()->with($conversation, 'Записал вас!', MessageStatus::Sent)->andReturn(new Message);

        $responder = Mockery::mock(BotResponder::class);
        $responder->shouldReceive('respond')->once()->with(Mockery::any(), $conversation, 'да, записывайте')
            ->andReturn(new BotReply('Записал вас!', escalate: false, booked: true));

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once()->with($conversation, 'да, записывайте');

        $reply = (new WebWidgetService($conversations, $messages, $responder, $contacts))
            ->reply($channel, $token, 'да, записывайте');

        $this->assertTrue($reply->booked);
    }
}
