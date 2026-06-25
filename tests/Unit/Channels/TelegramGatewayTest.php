<?php

declare(strict_types=1);

namespace Tests\Unit\Channels;

use App\Channels\Data\IncomingImage;
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

    public function test_download_image_picks_largest_photo_and_caption(): void
    {
        $jpeg = "\xFF\xD8\xFF\xE0".'BODY';
        Http::fake([
            '*/getFile*' => Http::response(['result' => ['file_path' => 'photos/file_9.jpg']]),
            '*/file/bot*' => Http::response($jpeg),
        ]);

        $image = $this->gateway()->downloadImage($this->channel(), ['message' => [
            'photo' => [
                ['file_id' => 'thumb', 'width' => 90],
                ['file_id' => 'largest', 'width' => 1280],
            ],
            'caption' => 'хочу такую стрижку',
        ]]);

        $this->assertInstanceOf(IncomingImage::class, $image);
        $this->assertSame($jpeg, $image->bytes);
        $this->assertSame('image/jpeg', $image->mimeType);
        $this->assertSame('хочу такую стрижку', $image->caption);
        Http::assertSent(fn ($request): bool => ! str_contains($request->url(), 'getFile')
            || str_contains($request->url(), 'file_id=largest'));
    }

    public function test_download_image_returns_null_without_photo(): void
    {
        $this->assertNull($this->gateway()->downloadImage($this->channel(), ['message' => ['text' => 'привет']]));
    }
}
