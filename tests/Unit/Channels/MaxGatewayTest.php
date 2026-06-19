<?php

declare(strict_types=1);

namespace Tests\Unit\Channels;

use App\Channels\Max\MaxGateway;
use App\DTO\ReplyKeyboard;
use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class MaxGatewayTest extends TestCase
{
    private function gateway(): MaxGateway
    {
        return new MaxGateway('https://botapi.max.ru');
    }

    private function channel(): Channel
    {
        $channel = new Channel;
        $channel->type = ChannelType::Max;
        $channel->credentials = ['access_token' => 'max-token'];

        return $channel;
    }

    public function test_send_posts_message_with_chat_id_and_auth_header(): void
    {
        Http::fake(['*/messages*' => Http::response(['message' => ['body' => ['mid' => 'm1']]])]);

        $this->gateway()->send($this->channel(), '555', 'привет');

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/messages')
                && str_contains($request->url(), 'chat_id=555')
                && $request['text'] === 'привет'
                && $request->hasHeader('Authorization', 'max-token');
        });
    }

    public function test_send_with_keyboard_attaches_inline_callback_buttons(): void
    {
        Http::fake(['*/messages*' => Http::response(['message' => ['body' => ['mid' => 'm1']]])]);

        $this->gateway()->send($this->channel(), '555', 'Выберите день', ReplyKeyboard::grid(['Пн 23.06', 'Вт 24.06'], 2));

        Http::assertSent(function ($request): bool {
            $attachment = $request['attachments'][0] ?? null;

            return $attachment !== null
                && $attachment['type'] === 'inline_keyboard'
                && $attachment['payload']['buttons'][0][0]['type'] === 'callback'
                && $attachment['payload']['buttons'][0][0]['text'] === 'Пн 23.06'
                && $attachment['payload']['buttons'][0][0]['payload'] === 'Пн 23.06';
        });
    }

    public function test_send_without_keyboard_has_no_attachments(): void
    {
        Http::fake(['*/messages*' => Http::response(['message' => ['body' => ['mid' => 'm1']]])]);

        $this->gateway()->send($this->channel(), '555', 'привет');

        Http::assertSent(fn ($request): bool => ! isset($request['attachments']));
    }

    public function test_parse_callback_extracts_payload_chat_and_callback_id(): void
    {
        $parsed = $this->gateway()->parseCallback([
            'update_type' => 'message_callback',
            'callback' => ['callback_id' => 'cb1', 'payload' => '10:00', 'user' => ['user_id' => 777]],
            'message' => ['recipient' => ['chat_id' => 555]],
        ]);

        $this->assertSame('555', $parsed['chatId']);
        $this->assertSame('10:00', $parsed['text']); // payload = выбор клиента
        $this->assertSame('cb1', $parsed['callbackId']);
    }

    public function test_parse_callback_ignores_non_callback(): void
    {
        $this->assertNull($this->gateway()->parseCallback(['update_type' => 'message_created', 'message' => []]));
    }

    public function test_answer_callback_posts_callback_id(): void
    {
        Http::fake(['*/answers*' => Http::response([])]);

        $this->gateway()->answerCallback($this->channel(), 'cb1');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/answers')
            && str_contains($request->url(), 'callback_id=cb1'));
    }

    public function test_get_me_returns_bot_info(): void
    {
        Http::fake(['*/me' => Http::response(['user_id' => 1, 'name' => 'My Bot', 'username' => 'my_bot', 'is_bot' => true])]);

        $me = $this->gateway()->getMe($this->channel());

        $this->assertSame('My Bot', $me['name']);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/me')
            && $request->hasHeader('Authorization', 'max-token'));
    }

    public function test_get_me_throws_on_invalid_token(): void
    {
        Http::fake(['*/me' => Http::response(['code' => 'verify.token'], 401)]);

        $this->expectException(RequestException::class);

        $this->gateway()->getMe($this->channel());
    }

    public function test_get_updates_returns_updates_and_marker(): void
    {
        Http::fake(['*/updates*' => Http::response([
            'updates' => [['update_type' => 'message_created', 'message' => ['recipient' => ['chat_id' => 555], 'body' => ['text' => 'эй', 'mid' => 'm1']]]],
            'marker' => 42,
        ])]);

        $result = $this->gateway()->getUpdates($this->channel(), null, 0);

        $this->assertSame(42, $result['marker']);
        $this->assertCount(1, $result['updates']);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/updates')
            && str_contains($request->url(), 'timeout=0'));
    }

    public function test_get_updates_passes_marker(): void
    {
        Http::fake(['*/updates*' => Http::response(['updates' => [], 'marker' => 100])]);

        $this->gateway()->getUpdates($this->channel(), 99, 0);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'marker=99'));
    }

    public function test_parse_message_extracts_chat_text_and_id(): void
    {
        $parsed = $this->gateway()->parseMessage([
            'update_type' => 'message_created',
            'message' => ['recipient' => ['chat_id' => 555], 'sender' => ['user_id' => 777], 'body' => ['text' => 'есть запись?', 'mid' => 'm7']],
        ]);

        $this->assertSame(['chatId' => '555', 'text' => 'есть запись?', 'id' => 'm7'], $parsed);
    }

    public function test_parse_message_ignores_non_message(): void
    {
        $this->assertNull($this->gateway()->parseMessage(['update_type' => 'bot_started', 'message' => []]));
    }

    public function test_parse_message_returns_envelope_with_empty_text_for_voice(): void
    {
        // Голосовое/вложение без текста — конверт с пустым текстом; решение по
        // нему (распознать голос) принимает джоб.
        $parsed = $this->gateway()->parseMessage([
            'update_type' => 'message_created',
            'message' => ['recipient' => ['chat_id' => 555], 'body' => ['text' => '   ', 'mid' => 'm9']],
        ]);

        $this->assertSame(['chatId' => '555', 'text' => '', 'id' => 'm9'], $parsed);
    }
}
