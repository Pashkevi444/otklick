<?php

declare(strict_types=1);

namespace App\Modules\Channels;

use App\Modules\Channels\Contracts\ChannelGateway;
use App\Shared\Enums\ChannelType;
use InvalidArgumentException;

/**
 * Реестр стратегий каналов: собирает зарегистрированные шлюзы по их
 * {@see ChannelGateway::provider()}. Добавить канал = реализовать ChannelGateway
 * и зарегистрировать его в теге `channel.gateways` (см. AppServiceProvider) —
 * менять резолвер не нужно.
 */
final class ChannelGatewayResolver
{
    /** @var array<string, ChannelGateway> */
    private array $gateways = [];

    /**
     * @param  iterable<ChannelGateway>  $gateways
     */
    public function __construct(iterable $gateways)
    {
        foreach ($gateways as $gateway) {
            $this->gateways[$gateway->provider()->value] = $gateway;
        }
    }

    public function for(ChannelType $type): ChannelGateway
    {
        return $this->gateways[$type->value]
            ?? throw new InvalidArgumentException("Нет шлюза для канала «{$type->value}».");
    }

    public function has(ChannelType $type): bool
    {
        return isset($this->gateways[$type->value]);
    }
}
