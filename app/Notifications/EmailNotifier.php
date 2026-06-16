<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DTO\OwnerNotification;
use App\Enums\NotificationChannelType;
use App\Mail\OwnerEventMail;
use App\Models\NotificationRecipient;
use App\Models\Tenant;
use App\Notifications\Contracts\Notifier;
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
