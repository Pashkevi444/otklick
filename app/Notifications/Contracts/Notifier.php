<?php

declare(strict_types=1);

namespace App\Notifications\Contracts;

use App\DTO\OwnerNotification;
use App\Enums\NotificationChannelType;
use App\Models\NotificationRecipient;
use App\Models\Tenant;

/**
 * Стратегия доставки уведомления по конкретному каналу (email/telegram/…).
 * Новый канал = новый класс с этим контрактом + тег notifiers.
 */
interface Notifier
{
    public function channel(): NotificationChannelType;

    public function send(Tenant $tenant, NotificationRecipient $recipient, OwnerNotification $notification): void;
}
