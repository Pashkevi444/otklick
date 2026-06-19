<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Channels\Contracts\ChannelGateway;
use App\DTO\ReplyKeyboard;
use App\Enums\ChannelType;
use App\Models\Channel;

/**
 * Тестовый шлюз канала: ничего не шлёт по сети, только записывает отправки —
 * чтобы проверять доставку рассылок без реальных HTTP-вызовов мессенджеров.
 */
final class RecordingChannelGateway implements ChannelGateway
{
    /** @var list<array{chatId: string, text: string}> */
    public array $sent = [];

    public function __construct(private readonly ChannelType $type) {}

    public function provider(): ChannelType
    {
        return $this->type;
    }

    public function send(Channel $channel, string $chatId, string $text, ?ReplyKeyboard $keyboard = null): void
    {
        $this->sent[] = ['chatId' => $chatId, 'text' => $text];
    }
}
