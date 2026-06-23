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
use App\Services\DealAutomationService;
use App\Services\SpamDetector;
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

        ['reply' => $reply] = (new WebWidgetService($conversations, $messages, $responder, $contacts, Mockery::mock(SpamDetector::class)->allows('isSpam')->andReturn(false)->getMock(), $this->pipeline()))
            ->reply($channel, $token, 'да, записывайте');

        $this->assertTrue($reply->booked);
    }

    public function test_operator_handling_silences_bot(): void
    {
        // Диалог перехвачен оператором → бот не отвечает (responder не зовём,
        // исходящее не пишем), reply пустой; курсор поллинга — id входящего.
        $channel = new Channel;
        $channel->id = 'web-1';
        $channel->setRelation('tenant', new Tenant(['name' => 'Бизнес']));

        $token = Crypt::encryptString('web-1|sess-1');
        $conversation = new Conversation;
        $conversation->operator_active_at = now(); // активный перехват

        $inbound = new Message;
        $inbound->id = 'm-in-1';

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('firstOrCreateForChat')->once()->andReturn($conversation);
        $conversations->shouldReceive('touchLastMessage')->once()->with($conversation);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recordInbound')->once()->andReturn($inbound);
        $messages->shouldNotReceive('recordOutbound');

        $responder = Mockery::mock(BotResponder::class);
        $responder->shouldNotReceive('respond');

        $contacts = Mockery::mock(ContactCapture::class);
        $contacts->shouldReceive('fromInbound')->once();

        ['reply' => $reply, 'lastId' => $lastId] = (new WebWidgetService($conversations, $messages, $responder, $contacts, Mockery::mock(SpamDetector::class)->allows('isSpam')->andReturn(false)->getMock(), $this->pipeline()))
            ->reply($channel, $token, 'есть кто живой?');

        $this->assertSame('', $reply->text);
        $this->assertSame('m-in-1', $lastId);
    }

    private function pipeline(): DealAutomationService
    {
        $pipeline = Mockery::mock(DealAutomationService::class);
        $pipeline->shouldReceive('onEvent')->byDefault();

        return $pipeline;
    }
}
