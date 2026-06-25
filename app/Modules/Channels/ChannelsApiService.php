<?php

declare(strict_types=1);

namespace App\Modules\Channels;

use App\Modules\Channels\Contracts\ChannelGateway;
use App\Modules\Channels\Contracts\ChannelsApi;
use App\Modules\Channels\Models\Channel;
use App\Modules\Channels\Repositories\Contracts\ChannelRepositoryInterface;
use App\Modules\Channels\Telegram\TelegramGateway;
use App\Shared\Enums\ChannelType;
use Illuminate\Support\Collection;

/**
 * Фасад модуля «Каналы»: реализует {@see ChannelsApi}, делегируя резолверу стратегий,
 * репозиторию каналов и (для getMe) Telegram-адаптеру. Имена методов совпадают с
 * внутренними — потребители меняют только тип в конструкторе.
 */
final class ChannelsApiService implements ChannelsApi
{
    public function __construct(
        private readonly ChannelGatewayResolver $gateways,
        private readonly ChannelRepositoryInterface $channels,
        private readonly TelegramGateway $telegram,
    ) {}

    public function for(ChannelType $type): ChannelGateway
    {
        return $this->gateways->for($type);
    }

    public function has(ChannelType $type): bool
    {
        return $this->gateways->has($type);
    }

    public function find(string $id): ?Channel
    {
        return $this->channels->find($id);
    }

    public function forCurrentTenant(): Collection
    {
        return $this->channels->forCurrentTenant();
    }

    public function getMe(Channel $channel): array
    {
        return $this->telegram->getMe($channel);
    }
}
