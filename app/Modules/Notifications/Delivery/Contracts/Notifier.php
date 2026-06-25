<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Delivery\Contracts;

use App\Modules\Notifications\DTO\OwnerNotification;
use App\Modules\Notifications\Models\NotificationRecipient;
use App\Shared\Enums\NotificationChannelType;
use App\Shared\Models\Tenant;

/**
 * Стратегия доставки уведомления по конкретному каналу (email/telegram/…).
 * Новый канал = новый класс с этим контрактом + тег notifiers.
 */
interface Notifier
{
    public function channel(): NotificationChannelType;

    public function send(Tenant $tenant, NotificationRecipient $recipient, OwnerNotification $notification): void;
}
