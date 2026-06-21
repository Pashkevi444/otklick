<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\AnnouncementType;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Repositories\Contracts\AnnouncementRepositoryInterface;
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

    public function paginateAllOfType(AnnouncementType $type, int $perPage): LengthAwarePaginator
    {
        return Announcement::query()
            ->where('type', $type)
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

    public function readIdsForCurrentTenant(): array
    {
        // AnnouncementRead скоупится текущим тенантом (TenantScope + RLS).
        return AnnouncementRead::query()->pluck('announcement_id')->all();
    }

    public function markReadForCurrentTenant(array $announcementIds, string $tenantId): void
    {
        foreach ($announcementIds as $id) {
            AnnouncementRead::query()->firstOrCreate(
                ['announcement_id' => $id, 'tenant_id' => $tenantId],
                ['read_at' => Carbon::now()],
            );
        }
    }

    public function unreadCountsForCurrentTenant(): array
    {
        $readIds = $this->readIdsForCurrentTenant();

        $count = fn (AnnouncementType $type): int => Announcement::query()
            ->where('type', $type)
            ->where('is_published', true)
            ->whereNotIn('id', $readIds)
            ->count();

        return [
            'news' => $count(AnnouncementType::News),
            'update' => $count(AnnouncementType::Update),
        ];
    }
}
