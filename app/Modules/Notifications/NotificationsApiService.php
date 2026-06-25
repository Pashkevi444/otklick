<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Channels\Models\Channel;
use App\Modules\Notifications\Contracts\NotificationsApi;
use App\Modules\Notifications\DTO\OwnerNotification;
use App\Modules\Notifications\Jobs\SendOwnerNotification;
use App\Modules\Notifications\Repositories\Contracts\NotificationRecipientRepositoryInterface;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Notifications\Services\TelegramLinkService;
use App\Modules\Notifications\Services\UserNotificationService;
use App\Shared\Enums\UserNotificationType;
use App\Shared\Models\Tenant;
use App\Shared\Models\User;
use Illuminate\Support\Collection;

/**
 * Фасад модуля «Уведомления»: реализует {@see NotificationsApi}, делегируя внутренним
 * сервисам / репозиторию / джобе. Имена методов совпадают с внутренними — потребители
 * меняют только тип в конструкторе.
 */
final class NotificationsApiService implements NotificationsApi
{
    public function __construct(
        private readonly UserNotificationService $userNotifications,
        private readonly NotificationService $ownerNotifications,
        private readonly TelegramLinkService $telegramLink,
        private readonly NotificationRecipientRepositoryInterface $recipients,
    ) {}

    public function notify(
        UserNotificationType $type,
        string $title,
        ?string $body = null,
        ?string $url = null,
        ?string $entityType = null,
        ?string $entityId = null,
    ): void {
        $this->userNotifications->notify($type, $title, $body, $url, $entityType, $entityId);
    }

    public function forUser(User $user): array
    {
        return $this->userNotifications->forUser($user);
    }

    public function unreadEntityIds(User $user, string $entityType): array
    {
        return $this->userNotifications->unreadEntityIds($user, $entityType);
    }

    public function markEntityRead(User $user, string $entityType, string $entityId): void
    {
        $this->userNotifications->markEntityRead($user, $entityType, $entityId);
    }

    public function markEntityTypeRead(User $user, string $entityType): void
    {
        $this->userNotifications->markEntityTypeRead($user, $entityType);
    }

    public function dispatchToOwners(Tenant $tenant, OwnerNotification $notification, ?callable $filter = null): void
    {
        $this->ownerNotifications->dispatchToOwners($tenant, $notification, $filter);
    }

    public function sendOwnerNotificationAsync(string $tenantId, string $event, array $context = []): void
    {
        SendOwnerNotification::dispatch($tenantId, $event, $context);
    }

    public function deliverableForCurrentTenant(): Collection
    {
        return $this->recipients->deliverableForCurrentTenant();
    }

    public function tryLink(Channel $channel, array $message): bool
    {
        return $this->telegramLink->tryLink($channel, $message);
    }
}
