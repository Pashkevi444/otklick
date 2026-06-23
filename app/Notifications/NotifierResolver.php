<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationChannelType;
use App\Notifications\Contracts\Notifier;

/**
 * Реестр нотификаторов по каналу (тот же паттерн, что BookingGatewayResolver).
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
