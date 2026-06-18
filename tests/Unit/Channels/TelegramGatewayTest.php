<?php

declare(strict_types=1);

namespace Tests\Unit\Channels;

use App\Channels\Telegram\TelegramGateway;
use App\DTO\ReplyKeyboard;
use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class TelegramGatewayTest extends TestCase
{
    private function gateway(): TelegramGateway
    {
        return new TelegramGateway('https://api.telegram.org');
    }

    private function channel(): Channel
    {
        $channel = new Channel;
        $channel->type = ChannelType::Telegram;
        $channel->credentials = ['bot_token' => 'bot-token'];

        return $channel;
    }

    public function test_send_with_keyboard_renders_reply_keyboard_markup(): void
    {
        Http::fake(['*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->gateway()->send($this->channel(), '555', 'Выберите день', ReplyKeyboard::grid(['Пн 23.06', 'Вт 24.06'], 2));

        Http::assertSent(function ($request): bool {
            $markup = json_decode((string) $request['reply_markup'], true);

            return is_array($markup)
                && $markup['one_time_keyboard'] === true
                && $markup['resize_keyboard'] === true
                && $markup['keyboard'][0][0]['text'] === 'Пн 23.06'
                && $markup['keyboard'][0][1]['text'] === 'Вт 24.06';
        });
    }

    public function test_send_without_keyboard_removes_previous_keyboard(): void
    {
        Http::fake(['*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

        $this->gateway()->send($this->channel(), '555', 'привет');

        Http::assertSent(function ($request): bool {
            $markup = json_decode((string) $request['reply_markup'], true);

            return is_array($markup) && ($markup['remove_keyboard'] ?? false) === true;
        });
    }
}
