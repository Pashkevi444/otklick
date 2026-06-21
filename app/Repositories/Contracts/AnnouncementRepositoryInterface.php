<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\AnnouncementType;
use App\Models\Announcement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AnnouncementRepositoryInterface
{
    /**
     * Опубликованные анонсы типа (для кабинета бизнеса), новые сверху.
     *
     * @return Collection<int, Announcement>
     */
    public function publishedOfType(AnnouncementType $type): Collection;

    /**
     * Опубликованные анонсы типа постранично (кабинет), новые сверху.
     *
     * @return LengthAwarePaginator<int, Announcement>
     */
    public function paginatePublishedOfType(AnnouncementType $type, int $perPage): LengthAwarePaginator;

    /**
     * Все анонсы типа постранично (админка СУ, включая черновики), новые сверху.
     *
     * @return LengthAwarePaginator<int, Announcement>
     */
    public function paginateAllOfType(AnnouncementType $type, int $perPage): LengthAwarePaginator;

    /** Опубликованный анонс типа по id (для детальной страницы бизнеса). */
    public function findPublished(string $id, AnnouncementType $type): ?Announcement;

    public function find(string $id): ?Announcement;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Announcement;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Announcement $announcement, array $attributes): void;

    public function delete(Announcement $announcement): void;

    /**
     * Id анонсов, уже прочитанных текущим тенантом.
     *
     * @return list<string>
     */
    public function readIdsForCurrentTenant(): array;

    /**
     * Пометить анонсы прочитанными текущим тенантом (идемпотентно).
     *
     * @param  list<string>  $announcementIds
     */
    public function markReadForCurrentTenant(array $announcementIds, string $tenantId): void;

    /**
     * Кол-во непрочитанных новостей для текущего тенанта.
     *
     * @return array{news: int}
     */
    public function unreadCountsForCurrentTenant(): array;
}
