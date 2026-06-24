<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\UserNotification;
use Illuminate\Support\Collection;

interface UserNotificationRepositoryInterface
{
    /**
     * Массовая вставка строк-уведомлений (фан-аут на получателей). Каждая строка —
     * полный набор атрибутов (id, tenant_id, user_id, type, …, created_at).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function insertMany(array $rows): void;

    /**
     * @return Collection<int, UserNotification>
     */
    public function recentForUser(string $userId, int $limit): Collection;

    /**
     * Непрочитанные по типам для пользователя.
     *
     * @return array<string, int> type.value => количество
     */
    public function unreadCountsByTypeForUser(string $userId): array;

    /** Пометить ВСЕ непрочитанные уведомления пользователя прочитанными («прочитать всё»). */
    public function markAllReadForUser(string $userId): void;

    /**
     * Пометить прочитанными уведомления пользователя по конкретной сущности
     * (открыл диалог/клиента/обработал пробел → его уведомления гаснут).
     */
    public function markEntityReadForUser(string $userId, string $entityType, string $entityId): void;
}
