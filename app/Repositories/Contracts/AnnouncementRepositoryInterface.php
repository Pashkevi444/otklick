<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\AnnouncementType;
use App\Models\Announcement;
use Illuminate\Support\Collection;

interface AnnouncementRepositoryInterface
{
    /**
     * Все анонсы типа (для админки СУ — включая черновики), новые сверху.
     *
     * @return Collection<int, Announcement>
     */
    public function allOfType(AnnouncementType $type): Collection;

    /**
     * Опубликованные анонсы типа (для кабинета бизнеса), новые сверху.
     *
     * @return Collection<int, Announcement>
     */
    public function publishedOfType(AnnouncementType $type): Collection;

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
     * Кол-во непрочитанного по типам для текущего тенанта.
     *
     * @return array{news: int, update: int}
     */
    public function unreadCountsForCurrentTenant(): array;
}
