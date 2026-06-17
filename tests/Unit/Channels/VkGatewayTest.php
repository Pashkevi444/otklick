<?php

declare(strict_types=1);

namespace Tests\Unit\Channels;

use App\Channels\Vk\VkGateway;
use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class VkGatewayTest extends TestCase
{
    private function gateway(): VkGateway
    {
        return new VkGateway('https://api.vk.com/method', '5.199');
    }

    private function channel(): Channel
    {
        $channel = new Channel;
        $channel->type = ChannelType::Vk;
        $channel->credentials = ['access_token' => 'community-token', 'group_id' => '42'];

        return $channel;
    }

    public function test_send_calls_messages_send_with_peer_and_group(): void
    {
        Http::fake(['*/messages.send' => Http::response(['response' => 123])]);

        $this->gateway()->send($this->channel(), '555', 'привет');

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/messages.send')
                && $request['peer_id'] === '555'
                && $request['message'] === 'привет'
                && $request['group_id'] === '42'
                && $request['access_token'] === 'community-token'
                && $request['v'] === '5.199';
        });
    }

    public function test_send_throws_on_vk_error_body(): void
    {
        // Ошибка 901 «нельзя писать без согласия» приходит как HTTP 200 + {error};
        // send должен бросить, чтобы исходящее записалось как Failed, а не Sent.
        Http::fake(['*/messages.send' => Http::response(['error' => ['error_code' => 901, 'error_msg' => 'no permission']], 200)]);

        $this->expectException(\RuntimeException::class);

        $this->gateway()->send($this->channel(), '555', 'привет');
    }

    public function test_group_name_reads_new_response_shape(): void
    {
        Http::fake(['*/groups.getById' => Http::response(['response' => ['groups' => [['name' => 'Барбершоп']]]])]);

        $this->assertSame('Барбершоп', $this->gateway()->groupName($this->channel()));
    }

    public function test_group_name_reads_legacy_response_shape(): void
    {
        Http::fake(['*/groups.getById' => Http::response(['response' => [['name' => 'Старый формат']]])]);

        $this->assertSame('Старый формат', $this->gateway()->groupName($this->channel()));
    }

    public function test_group_name_throws_on_vk_error_body(): void
    {
        // VK отдаёт ошибки HTTP 200 с телом {error}; не должны принять это за «нет имени».
        Http::fake(['*/groups.getById' => Http::response(['error' => ['error_code' => 5, 'error_msg' => 'bad token']], 200)]);

        $this->expectException(\RuntimeException::class);

        $this->gateway()->groupName($this->channel());
    }

    public function test_long_poll_server_returns_connection_info(): void
    {
        Http::fake(['*/groups.getLongPollServer' => Http::response([
            'response' => ['server' => 'https://lp.vk.com/wh123', 'key' => 'secretkey', 'ts' => '100'],
        ])]);

        $this->assertSame(
            ['server' => 'https://lp.vk.com/wh123', 'key' => 'secretkey', 'ts' => '100'],
            $this->gateway()->longPollServer($this->channel()),
        );
    }

    public function test_get_updates_polls_a_check_endpoint(): void
    {
        Http::fake(['lp.vk.com/*' => Http::response([
            'ts' => '101',
            'updates' => [['type' => 'message_new', 'object' => ['message' => ['peer_id' => 555, 'text' => 'эй']]]],
        ])]);

        $result = $this->gateway()->getUpdates('https://lp.vk.com/wh123', 'secretkey', '100', 0);

        $this->assertSame('101', $result['ts']);
        $this->assertCount(1, $result['updates']);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'act=a_check')
            && str_contains($request->url(), 'key=secretkey')
            && str_contains($request->url(), 'ts=100'));
    }

    public function test_parse_message_extracts_peer_text_and_id(): void
    {
        $parsed = $this->gateway()->parseMessage([
            'type' => 'message_new',
            'object' => ['message' => ['peer_id' => 555, 'from_id' => 555, 'text' => 'есть запись?', 'conversation_message_id' => 7]],
        ]);

        $this->assertSame(['peerId' => '555', 'text' => 'есть запись?', 'id' => '7'], $parsed);
    }

    public function test_parse_message_ignores_non_message_events(): void
    {
        $this->assertNull($this->gateway()->parseMessage(['type' => 'group_join', 'object' => []]));
        $this->assertNull($this->gateway()->parseMessage([
            'type' => 'message_new',
            'object' => ['message' => ['peer_id' => 555, 'text' => '   ']],
        ]));
    }
}
