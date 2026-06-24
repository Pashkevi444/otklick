<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserNotificationType;
use App\Events\NotificationsUpdated;
use App\Models\User;
use App\Models\UserNotification;
use App\Repositories\Contracts\UserNotificationRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Str;
use Throwable;

/**
 * In-app уведомления (колокольчик + бейджи плашек). Создаёт события фан-аутом на
 * пользователей тенанта С УЧЁТОМ матрицы прав (`MemberPermission`) — сотрудник без
 * доступа к разделу уведомление не получает. Read-state — пер-юзер.
 *
 * Вызывается в тенант-контексте (хуки бота). За доставку «вживую» отвечает WS-пинг
 * (broadcast), но данные считаются здесь — поэтому работает и на поллинге.
 */
final readonly class UserNotificationService
{
    public function __construct(
        private UserNotificationRepositoryInterface $notifications,
        private UserRepositoryInterface $users,
    ) {}

    /**
     * Создаёт уведомление для всех пользователей текущего тенанта, у кого есть
     * право видеть этот тип события.
     */
    public function notify(
        UserNotificationType $type,
        string $title,
        ?string $body = null,
        ?string $url = null,
        ?string $entityType = null,
        ?string $entityId = null,
    ): void {
        $now = now();
        $permission = $type->requiredPermission()->value;
        $rows = [];

        foreach ($this->users->forCurrentTenant() as $user) {
            if (! $user->allows($permission)) {
                continue;
            }

            $rows[] = [
                'id' => (string) Str::uuid(),
                'tenant_id' => (string) $user->tenant_id,
                'user_id' => (string) $user->id,
                'type' => $type->value,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'title' => $title,
                'body' => $body,
                'url' => $url,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return; // никому не положено это видеть — ни вставки, ни пинга
        }

        $this->notifications->insertMany($rows);

        // WS-пинг «обновись» на канал тенанта (клиенты перезапросят свою выдачу).
        // Best-effort: упавший Reverb не должен ронять хранение/бота — есть поллинг.
        try {
            broadcast(new NotificationsUpdated((string) $rows[0]['tenant_id']));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Выдача для шапки/бейджей: общий счётчик непрочитанного, счётчики по
     * разделам-плашкам и последние события для колокольчика.
     *
     * @return array{total: int, sections: array<string, int>, items: list<array<string, mixed>>}
     */
    public function forUser(User $user): array
    {
        $total = 0;
        $sections = [];

        foreach ($this->notifications->unreadCountsByTypeForUser((string) $user->id) as $typeValue => $count) {
            $type = UserNotificationType::tryFrom((string) $typeValue);

            if ($type === null) {
                continue;
            }

            $sections[$type->section()] = ($sections[$type->section()] ?? 0) + $count;
            $total += $count;
        }

        return [
            'total' => $total,
            'sections' => $sections,
            'items' => $this->notifications->recentForUser((string) $user->id, 20)
                ->map(fn (UserNotification $n): array => [
                    'id' => $n->id,
                    'type' => $n->type->value,
                    'icon' => $n->type->icon(),
                    'title' => $n->title,
                    'body' => $n->body,
                    'url' => $n->url,
                    'read' => $n->read_at !== null,
                    'at' => $n->created_at->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /** Открыл/просмотрел сущность (диалог/клиента/обработал пробел) → её уведомления гаснут. */
    public function markEntityRead(User $user, string $entityType, string $entityId): void
    {
        $this->notifications->markEntityReadForUser((string) $user->id, $entityType, $entityId);
    }

    public function markAllRead(User $user): void
    {
        $this->notifications->markAllReadForUser((string) $user->id);
    }
}
