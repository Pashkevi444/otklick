<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Delivery;

use App\Modules\Notifications\Delivery\Contracts\Notifier;
use App\Modules\Notifications\DTO\OwnerNotification;
use App\Modules\Notifications\Mail\OwnerEventMail;
use App\Modules\Notifications\Models\NotificationRecipient;
use App\Shared\Enums\NotificationChannelType;
use App\Shared\Models\Tenant;
use Illuminate\Support\Facades\Mail;

final class EmailNotifier implements Notifier
{
    public function channel(): NotificationChannelType
    {
        return NotificationChannelType::Email;
    }

    public function send(Tenant $tenant, NotificationRecipient $recipient, OwnerNotification $notification): void
    {
        Mail::to((string) $recipient->value)->send(new OwnerEventMail($notification));
    }
}
