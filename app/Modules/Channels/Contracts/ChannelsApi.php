<?php

declare(strict_types=1);

namespace App\Modules\Channels\Contracts;

use App\Modules\Channels\ChannelsApiService;
use App\Modules\Channels\Models\Channel;
use App\Shared\Enums\ChannelType;
use Illuminate\Support\Collection;

/**
 * Публичный контракт модуля «Каналы» — дверь для других модулей. Резолвер стратегий
 * (по ChannelType) и репозиторий каналов наружу отдаются через этот фасад; конкретные
 * адаптеры (Telegram/VK/MAX/WhatsApp) и поллинг — приватны. Для отправки достаточно
 * общего {@see MessengerGateway} (он Open). Реализация — {@see ChannelsApiService}.
 */
interface ChannelsApi
{
    /** Стратегия канала по типу (умеет отправлять — ChannelGateway extends MessengerGateway). */
    public function for(ChannelType $type): ChannelGateway;

    public function has(ChannelType $type): bool;

    public function find(string $id): ?Channel;

    /** @return Collection<int, Channel> */
    public function forCurrentTenant(): Collection;

    /**
     * Информация о боте канала (валидация подключения, Telegram getMe).
     *
     * @return array<string, mixed>
     */
    public function getMe(Channel $channel): array;
}
