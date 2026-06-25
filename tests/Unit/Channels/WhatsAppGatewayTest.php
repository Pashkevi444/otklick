<?php

declare(strict_types=1);

namespace Tests\Unit\Channels;

use App\Modules\Channels\Models\Channel;
use App\Modules\Channels\WhatsApp\WhatsAppGateway;
use App\Shared\DTO\ReplyKeyboard;
use App\Shared\Enums\ChannelType;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class WhatsAppGatewayTest extends TestCase
{
    private function gateway(): WhatsAppGateway
    {
        return new WhatsAppGateway('https://api.green-api.com');
    }

    private function channel(): Channel
    {
        $channel = new Channel;
        $channel->type = ChannelType::WhatsApp;
        $channel->credentials = ['id_instance' => '1101', 'api_token' => 'tok'];

        return $channel;
    }

    public function test_send_posts_message_to_green_api(): void
    {
        Http::fake(['*/sendMessage/*' => Http::response(['idMessage' => 'X'])]);

        $this->gateway()->send($this->channel(), '79991234567@c.us', 'привет');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/waInstance1101/sendMessage/tok')
            && $request['chatId'] === '79991234567@c.us'
            && $request['message'] === 'привет');
    }

    public function test_send_appends_keyboard_labels_as_text(): void
    {
        Http::fake(['*/sendMessage/*' => Http::response(['idMessage' => 'X'])]);

        $this->gateway()->send($this->channel(), '79991234567@c.us', 'Выберите время', new ReplyKeyboard([['10:00', '12:00']]));

        Http::assertSent(fn ($request): bool => str_contains((string) $request['message'], 'Выберите время')
            && str_contains((string) $request['message'], '10:00')
            && str_contains((string) $request['message'], '12:00'));
    }

    public function test_parse_message_extracts_chat_text_and_id(): void
    {
        $parsed = $this->gateway()->parseMessage([
            'typeWebhook' => 'incomingMessageReceived',
            'idMessage' => 'ABC',
            'senderData' => ['chatId' => '79991234567@c.us'],
            'messageData' => ['typeMessage' => 'textMessage', 'textMessageData' => ['textMessage' => 'есть запись?']],
        ]);

        $this->assertSame(['chatId' => '79991234567@c.us', 'text' => 'есть запись?', 'id' => 'ABC'], $parsed);
    }

    public function test_parse_message_ignores_non_incoming(): void
    {
        $this->assertNull($this->gateway()->parseMessage(['typeWebhook' => 'outgoingMessageStatus']));
    }

    public function test_parse_message_returns_empty_text_for_voice(): void
    {
        $parsed = $this->gateway()->parseMessage([
            'typeWebhook' => 'incomingMessageReceived',
            'idMessage' => 'V',
            'senderData' => ['chatId' => '79991234567@c.us'],
            'messageData' => ['typeMessage' => 'audioMessage', 'fileMessageData' => ['downloadUrl' => 'https://x/a.ogg']],
        ]);

        $this->assertSame('', $parsed['text']);
    }

    public function test_state_instance_returns_authorization_state(): void
    {
        Http::fake(['*/getStateInstance/*' => Http::response(['stateInstance' => 'authorized'])]);

        $this->assertSame('authorized', $this->gateway()->stateInstance($this->channel()));
    }
}
