<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\DTO\OwnerNotification;
use App\Enums\NotificationChannelType;
use App\Models\NotificationRecipient;
use App\Models\Tenant;
use App\Notifications\Contracts\Notifier;
use App\Notifications\NotifierResolver;
use PHPUnit\Framework\TestCase;

final class NotifierResolverTest extends TestCase
{
    public function test_resolves_notifier_by_channel(): void
    {
        $email = $this->fakeNotifier(NotificationChannelType::Email);
        $telegram = $this->fakeNotifier(NotificationChannelType::Telegram);

        $resolver = new NotifierResolver([$email, $telegram]);

        $this->assertSame($email, $resolver->for(NotificationChannelType::Email));
        $this->assertSame($telegram, $resolver->for(NotificationChannelType::Telegram));
    }

    public function test_returns_null_for_unregistered_channel(): void
    {
        $resolver = new NotifierResolver([$this->fakeNotifier(NotificationChannelType::Email)]);

        $this->assertNull($resolver->for(NotificationChannelType::Telegram));
    }

    private function fakeNotifier(NotificationChannelType $channel): Notifier
    {
        return new class($channel) implements Notifier
        {
            public function __construct(private NotificationChannelType $channel) {}

            public function channel(): NotificationChannelType
            {
                return $this->channel;
            }

            public function send(Tenant $tenant, NotificationRecipient $recipient, OwnerNotification $notification): void {}
        };
    }
}
