<?php

declare(strict_types=1);

namespace App\Booking;

use App\Booking\Contracts\BookingGateway;
use App\Enums\CrmProvider;
use InvalidArgumentException;

/**
 * Реестр шлюзов записи: собирает все зарегистрированные {@see BookingGateway} по
 * их {@see BookingGateway::provider()}. Добавить нового провайдера записи =
 * реализовать BookingGateway и зарегистрировать его в теге `booking.gateways`
 * (см. AppServiceProvider) — менять резолвер не нужно.
 */
final class BookingGatewayResolver
{
    /** @var array<string, BookingGateway> */
    private array $gateways = [];

    /**
     * @param  iterable<BookingGateway>  $gateways
     */
    public function __construct(iterable $gateways)
    {
        foreach ($gateways as $gateway) {
            $this->gateways[$gateway->provider()->value] = $gateway;
        }
    }

    public function for(CrmProvider $provider): BookingGateway
    {
        return $this->gateways[$provider->value]
            ?? throw new InvalidArgumentException("Нет шлюза для CRM «{$provider->value}».");
    }
}
