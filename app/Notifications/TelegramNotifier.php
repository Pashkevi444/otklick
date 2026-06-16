<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Channels\Telegram\TelegramGateway;
use App\DTO\OwnerNotification;
use App\Enums\ChannelType;
use App\Enums\NotificationChannelType;
use App\Models\Channel;
use App\Models\NotificationRecipient;
use App\Models\Tenant;
use App\Notifications\Contracts\Notifier;
use App\Repositories\Contracts\ChannelRepositoryInterface;

/**
 * Шлёт уведомление в Telegram через бот самого бизнеса (его токен) на chat_id
 * получателя. Если у бизнеса нет активного Telegram-канала — слать нечем,
 * тихо пропускаем (есть email-канал).
 */
final readonly class TelegramNotifier implements Notifier
{
    public function __construct(
        private ChannelRepositoryInterface $channels,
        private TelegramGateway $telegram,
    ) {}

    public function channel(): NotificationChannelType
    {
        return NotificationChannelType::Telegram;
    }

    public function send(Tenant $tenant, NotificationRecipient $recipient, OwnerNotification $notification): void
    {
        $channel = $this->channels->forCurrentTenant()
            ->first(fn (Channel $c): bool => $c->type === ChannelType::Telegram && $c->is_active);

        if (! $channel instanceof Channel) {
            return;
        }

        $this->telegram->send($channel, (string) $recipient->value, $notification->subject."\n\n".$notification->body);
    }
}
