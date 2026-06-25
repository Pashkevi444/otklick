<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Contracts;

use App\Modules\Channels\Models\Channel;
use App\Modules\Notifications\DTO\OwnerNotification;
use App\Modules\Notifications\Models\NotificationRecipient;
use App\Modules\Notifications\NotificationsApiService;
use App\Shared\Enums\UserNotificationType;
use App\Shared\Models\Tenant;
use App\Shared\Models\User;
use Illuminate\Support\Collection;

/**
 * Публичный контракт модуля «Уведомления» — единственная дверь для других модулей.
 * Снаружи доступны только эти методы; UserNotificationService / NotificationService /
 * TelegramLinkService / репозитории / джобы — приватная кухня модуля.
 * Реализация — {@see NotificationsApiService}.
 */
interface NotificationsApi
{
    // ── In-app уведомления (колокольчик + бейджи плашек) ──

    /**
     * Создаёт in-app уведомление для всех пользователей текущего тенанта, у кого
     * есть право видеть этот тип события.
     */
    public function notify(
        UserNotificationType $type,
        string $title,
        ?string $body = null,
        ?string $url = null,
        ?string $entityType = null,
        ?string $entityId = null,
    ): void;

    /**
     * Выдача для шапки/бейджей: общий счётчик, счётчики по разделам и последние
     * события для колокольчика.
     *
     * @return array{total: int, sections: array<string, int>, items: list<array<string, mixed>>}
     */
    public function forUser(User $user): array;

    /**
     * Id сущностей с непрочитанными уведомлениями (подсветка новых строк списка).
     *
     * @return list<string>
     */
    public function unreadEntityIds(User $user, string $entityType): array;

    /** Открыл/просмотрел сущность → её уведомления гаснут. */
    public function markEntityRead(User $user, string $entityType, string $entityId): void;

    /** «Прочитать всё» в списке → гасим все уведомления его сущностей. */
    public function markEntityTypeRead(User $user, string $entityType): void;

    // ── Исходящие владельцу бизнеса (почта / Telegram) ──

    /**
     * Рассылает готовое уведомление доставляемым получателям бизнеса. Необязательный
     * $filter ограничивает получателей (по подписке на тип / по роли).
     *
     * @param  null|callable(NotificationRecipient): bool  $filter
     */
    public function dispatchToOwners(Tenant $tenant, OwnerNotification $notification, ?callable $filter = null): void;

    /**
     * Фоновая рассылка уведомления владельцу о событии (Horizon, восстанавливает
     * тенант-контекст из tenantId).
     *
     * @param  array<string, mixed>  $context
     */
    public function sendOwnerNotificationAsync(string $tenantId, string $event, array $context = []): void;

    /**
     * Готовые к доставке получатели текущего тенанта (активны и подтверждены).
     *
     * @return Collection<int, NotificationRecipient>
     */
    public function deliverableForCurrentTenant(): Collection;

    // ── Привязка Telegram-получателя уведомлений по диплинку ──

    /**
     * @param  array<string, mixed>  $message
     * @return bool true — апдейт был командой привязки и обработан (не диалог)
     */
    public function tryLink(Channel $channel, array $message): bool;
}
