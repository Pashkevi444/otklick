<?php

declare(strict_types=1);

namespace App\Channels\Contracts;

use App\Channels\ChannelGatewayResolver;
use App\Enums\ChannelType;

/**
 * Стратегия канала общения: знает свой тип и умеет отправлять сообщение клиенту.
 * Выбирается по {@see ChannelType} через {@see ChannelGatewayResolver}.
 * Бизнес-логика (бот, мост, напоминания) работает с этим контрактом, не зная
 * конкретного мессенджера. Новый канал = новый ChannelGateway + регистрация в
 * теге `channel.gateways`.
 */
interface ChannelGateway extends MessengerGateway
{
    public function provider(): ChannelType;
}
