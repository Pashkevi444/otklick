<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Repositories\Eloquent;

use App\Modules\Notifications\Models\UserNotification;
use App\Modules\Notifications\Repositories\Contracts\UserNotificationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentUserNotificationRepository implements UserNotificationRepositoryInterface
{
    public function paginatedForUser(string $userId, int $perPage, ?array $types): LengthAwarePaginator
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->when($types !== null, fn ($q) => $q->whereIn('type', $types))
            ->latest()
            ->paginate($perPage);
    }

    public function insertMany(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        UserNotification::query()->insert($rows);
    }

    public function recentForUser(string $userId, int $limit): Collection
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function unreadCountsByTypeForUser(string $userId): array
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->selectRaw('type, count(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type')
            ->map(fn ($c): int => (int) $c)
            ->all();
    }

    public function markAllReadForUser(string $userId): void
    {
        UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function markEntityReadForUser(string $userId, string $entityType, string $entityId): void
    {
        UserNotification::query()
            ->where('user_id', $userId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function unreadEntityIdsForUser(string $userId, string $entityType): array
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->where('entity_type', $entityType)
            ->whereNull('read_at')
            ->whereNotNull('entity_id')
            ->pluck('entity_id')
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function markEntityTypeReadForUser(string $userId, string $entityType): void
    {
        UserNotification::query()
            ->where('user_id', $userId)
            ->where('entity_type', $entityType)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
