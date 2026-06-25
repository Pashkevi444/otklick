<?php

declare(strict_types=1);

namespace App\Modules\Booking\Crm;

use App\Modules\Booking\Crm\Contracts\CrmGateway;
use App\Shared\Enums\CrmProvider;
use InvalidArgumentException;

/**
 * Реестр стратегий CRM: собирает все зарегистрированные шлюзы по их
 * {@see CrmGateway::provider()}. Добавить новую CRM = реализовать CrmGateway и
 * зарегистрировать его в теге `crm.gateways` (см. AppServiceProvider) — менять
 * резолвер не нужно.
 */
final class CrmGatewayResolver
{
    /** @var array<string, CrmGateway> */
    private array $gateways = [];

    /**
     * @param  iterable<CrmGateway>  $gateways
     */
    public function __construct(iterable $gateways)
    {
        foreach ($gateways as $gateway) {
            $this->gateways[$gateway->provider()->value] = $gateway;
        }
    }

    public function for(CrmProvider $provider): CrmGateway
    {
        return $this->gateways[$provider->value]
            ?? throw new InvalidArgumentException("Нет шлюза для CRM «{$provider->value}».");
    }
}
