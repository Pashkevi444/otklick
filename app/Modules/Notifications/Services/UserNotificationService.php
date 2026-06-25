<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Identity\Contracts\IdentityApi;
use App\Modules\Notifications\Events\NotificationsUpdated;
use App\Modules\Notifications\Models\UserNotification;
use App\Modules\Notifications\Repositories\Contracts\UserNotificationRepositoryInterface;
use App\Shared\Enums\UserNotificationType;
use App\Shared\Models\User;
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
        private IdentityApi $users,
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
                ->map($this->present(...))
                ->all(),
        ];
    }

    /**
     * Журнал «все мои уведомления» (отдельная страница): пагинированная история с
     * необязательным фильтром по разделу. Раздел → типы разворачивается здесь
     * (доменный словарь), репозиторий лишь фильтрует и пагинирует. Просмотр журнала
     * НЕ гасит уведомления — гаснут пер-элемент (открыл сущность) или «прочитать всё».
     *
     * @return array{notifications: list<array<string, mixed>>, pagination: array{current: int, last: int, total: int, from: int|null, to: int|null}}
     */
    public function historyForUser(User $user, ?string $section, int $perPage = 20): array
    {
        $types = $section === null ? null : array_values(array_map(
            fn (UserNotificationType $t): string => $t->value,
            array_filter(
                UserNotificationType::cases(),
                fn (UserNotificationType $t): bool => $t->section() === $section,
            ),
        ));

        $page = $this->notifications->paginatedForUser((string) $user->id, $perPage, $types);

        return [
            'notifications' => array_map($this->present(...), $page->items()),
            'pagination' => [
                'current' => $page->currentPage(),
                'last' => $page->lastPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
        ];
    }

    /**
     * Единый формат элемента уведомления для фронта (колокольчик + журнал).
     *
     * @return array<string, mixed>
     */
    private function present(UserNotification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type->value,
            'icon' => $n->type->icon(),
            'title' => $n->title,
            'body' => $n->body,
            'url' => $n->url,
            'read' => $n->read_at !== null,
            'at' => $n->created_at->toIso8601String(),
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

    /**
     * Id сущностей с непрочитанными уведомлениями (для подсветки новых строк списка).
     *
     * @return list<string>
     */
    public function unreadEntityIds(User $user, string $entityType): array
    {
        return $this->notifications->unreadEntityIdsForUser((string) $user->id, $entityType);
    }

    /** Кнопка «Прочитать всё» в списке (клиенты/лиды) → гасим все уведомления его сущностей. */
    public function markEntityTypeRead(User $user, string $entityType): void
    {
        $this->notifications->markEntityTypeReadForUser((string) $user->id, $entityType);
    }
}
