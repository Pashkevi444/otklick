<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AnnouncementType;
use App\Models\Announcement;
use App\Repositories\Contracts\AnnouncementRepositoryInterface;
use Illuminate\Support\Carbon;

/**
 * Анонсы площадки (новости и обновления): публикация супер-админом и лента для
 * бизнеса с отметкой прочитанного.
 */
final readonly class AnnouncementService
{
    public function __construct(private AnnouncementRepositoryInterface $announcements) {}

    /**
     * Лента для админки СУ (включая черновики).
     *
     * @return list<array<string, mixed>>
     */
    public function adminList(AnnouncementType $type): array
    {
        return $this->announcements->allOfType($type)
            ->map(static fn (Announcement $a): array => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'is_published' => $a->is_published,
                'published_at' => $a->published_at?->toDateTimeString(),
                'created_at' => $a->created_at?->toDateString(),
            ])
            ->all();
    }

    public function create(AnnouncementType $type, string $title, string $body, bool $publish): Announcement
    {
        return $this->announcements->create([
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'is_published' => $publish,
            'published_at' => $publish ? Carbon::now() : null,
        ]);
    }

    public function update(string $id, string $title, string $body, bool $publish): ?Announcement
    {
        $announcement = $this->announcements->find($id);

        if ($announcement === null) {
            return null;
        }

        $this->announcements->update($announcement, [
            'title' => $title,
            'body' => $body,
            'is_published' => $publish,
            // Дата публикации проставляется при первой публикации и не сбрасывается.
            'published_at' => $publish ? ($announcement->published_at ?? Carbon::now()) : null,
        ]);

        return $announcement;
    }

    public function delete(string $id): bool
    {
        $announcement = $this->announcements->find($id);

        if ($announcement === null) {
            return false;
        }

        $this->announcements->delete($announcement);

        return true;
    }

    /**
     * Лента опубликованных анонсов для бизнеса с флагом «новое» (непрочитанное).
     * Побочный эффект: помечает показанные анонсы прочитанными этим тенантом —
     * после открытия раздела бейдж непрочитанного гаснет.
     *
     * @return list<array<string, mixed>>
     */
    public function cabinetFeed(AnnouncementType $type, string $tenantId): array
    {
        $published = $this->announcements->publishedOfType($type);
        $readIds = $this->announcements->readIdsForCurrentTenant();

        $items = $published
            ->map(static fn (Announcement $a): array => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'published_at' => $a->published_at?->toDateTimeString(),
                'is_new' => ! in_array($a->id, $readIds, true),
            ])
            ->all();

        $this->announcements->markReadForCurrentTenant($published->pluck('id')->all(), $tenantId);

        return $items;
    }

    /**
     * Непрочитанное по типам для подсветки пунктов меню.
     *
     * @return array{news: int, update: int}
     */
    public function unreadCounts(): array
    {
        return $this->announcements->unreadCountsForCurrentTenant();
    }
}
