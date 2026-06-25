<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Channels\Contracts\ChannelGateway;
use App\Modules\Channels\Models\Channel;
use App\Shared\DTO\ReplyKeyboard;
use App\Shared\Enums\ChannelType;
use RuntimeException;

/**
 * Тестовый шлюз канала: ничего не шлёт по сети, только записывает отправки —
 * чтобы проверять доставку рассылок без реальных HTTP-вызовов мессенджеров.
 * При $throws=true имитирует сбой канала (для проверки журнала ошибок).
 */
final class RecordingChannelGateway implements ChannelGateway
{
    /** @var list<array{chatId: string, text: string, images: list<string>}> */
    public array $sent = [];

    public function __construct(
        private readonly ChannelType $type,
        private readonly bool $throws = false,
    ) {}

    public function provider(): ChannelType
    {
        return $this->type;
    }

    public function send(Channel $channel, string $chatId, string $text, ?ReplyKeyboard $keyboard = null, array $images = []): void
    {
        if ($this->throws) {
            throw new RuntimeException('boom');
        }

        $this->sent[] = ['chatId' => $chatId, 'text' => $text, 'images' => $images];
    }
}
