<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Delivery;

use App\Modules\Notifications\Delivery\Contracts\Notifier;
use App\Shared\Enums\NotificationChannelType;

/**
 * Реестр нотификаторов по каналу (тот же паттерн, что CrmGatewayResolver).
 */
final class NotifierResolver
{
    /** @var array<string, Notifier> */
    private array $notifiers = [];

    /**
     * @param  iterable<Notifier>  $notifiers
     */
    public function __construct(iterable $notifiers)
    {
        foreach ($notifiers as $notifier) {
            $this->notifiers[$notifier->channel()->value] = $notifier;
        }
    }

    public function for(NotificationChannelType $channel): ?Notifier
    {
        return $this->notifiers[$channel->value] ?? null;
    }
}
