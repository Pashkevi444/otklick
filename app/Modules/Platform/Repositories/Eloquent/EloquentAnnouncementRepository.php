<?php

declare(strict_types=1);

namespace App\Modules\Platform\Repositories\Eloquent;

use App\Modules\Platform\Models\Announcement;
use App\Modules\Platform\Models\AnnouncementRead;
use App\Modules\Platform\Repositories\Contracts\AnnouncementRepositoryInterface;
use App\Shared\Enums\AnnouncementType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class EloquentAnnouncementRepository implements AnnouncementRepositoryInterface
{
    public function publishedOfType(AnnouncementType $type): Collection
    {
        return $this->publishedQuery($type)->get();
    }

    public function paginatePublishedOfType(AnnouncementType $type, int $perPage): LengthAwarePaginator
    {
        return $this->publishedQuery($type)->paginate($perPage)->withQueryString();
    }

    public function paginateAllOfType(AnnouncementType $type, int $perPage, ?string $search = null): LengthAwarePaginator
    {
        $term = $search !== null ? mb_strtolower(trim($search)) : '';

        return Announcement::query()
            ->where('type', $type)
            ->when($term !== '', function ($query) use ($term): void {
                // LOWER(...) LIKE — кросс-СУБД регистронезависимый поиск (pg + sqlite).
                $query->where(function ($q) use ($term): void {
                    $q->whereRaw('LOWER(title) LIKE ?', ['%'.$term.'%'])
                        ->orWhereRaw('LOWER(body) LIKE ?', ['%'.$term.'%']);
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findPublished(string $id, AnnouncementType $type): ?Announcement
    {
        return $this->publishedQuery($type)->whereKey($id)->first();
    }

    public function find(string $id): ?Announcement
    {
        return Announcement::query()->find($id);
    }

    /**
     * Опубликованные анонсы типа, новые сверху.
     *
     * @return Builder<Announcement>
     */
    private function publishedQuery(AnnouncementType $type): Builder
    {
        return Announcement::query()
            ->where('type', $type)
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->orderByDesc('created_at');
    }

    public function create(array $attributes): Announcement
    {
        return Announcement::query()->create($attributes);
    }

    public function update(Announcement $announcement, array $attributes): void
    {
        $announcement->update($attributes);
    }

    public function delete(Announcement $announcement): void
    {
        $announcement->delete();
    }

    public function readIdsForUser(string $userId): array
    {
        // AnnouncementRead скоупится текущим тенантом (TenantScope + RLS) + пер-юзер.
        return AnnouncementRead::query()->where('user_id', $userId)->pluck('announcement_id')->all();
    }

    public function markReadForUser(array $announcementIds, string $tenantId, string $userId): void
    {
        foreach ($announcementIds as $id) {
            AnnouncementRead::query()->firstOrCreate(
                ['announcement_id' => $id, 'user_id' => $userId],
                ['tenant_id' => $tenantId, 'read_at' => Carbon::now()],
            );
        }
    }

    public function unreadCountsForUser(string $userId): array
    {
        $readIds = $this->readIdsForUser($userId);

        return [
            'news' => Announcement::query()
                ->where('type', AnnouncementType::News)
                ->where('is_published', true)
                ->whereNotIn('id', $readIds)
                ->count(),
        ];
    }
}
