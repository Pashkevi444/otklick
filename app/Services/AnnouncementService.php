<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AnnouncementType;
use App\Models\Announcement;
use App\Repositories\Contracts\AnnouncementRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Анонсы площадки (новости и обновления): публикация супер-админом (текст —
 * форматированный HTML с картинками) и лента для бизнеса с отметкой прочитанного.
 */
final readonly class AnnouncementService
{
    private const int PER_PAGE = 10;

    public function __construct(private AnnouncementRepositoryInterface $announcements) {}

    /**
     * Лента для админки СУ постранично (включая черновики), с датами.
     *
     * @return array{data: list<array<string, mixed>>, current_page: int, last_page: int, total: int}
     */
    public function adminPaginated(AnnouncementType $type): array
    {
        return $this->paginate(
            $this->announcements->paginateAllOfType($type, self::PER_PAGE),
            fn (Announcement $a): array => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'excerpt' => $this->excerpt($a->body),
                'is_published' => $a->is_published,
                'published_at' => $a->published_at?->toDateTimeString(),
                'created_at' => $a->created_at?->toDateTimeString(),
            ],
        );
    }

    public function create(AnnouncementType $type, string $title, string $body, bool $publish): Announcement
    {
        return $this->announcements->create([
            'type' => $type,
            'title' => $title,
            'body' => $this->sanitize($body),
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
            'body' => $this->sanitize($body),
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
     * Лента опубликованных анонсов для бизнеса постранично, с флагом «новое».
     * Побочный эффект: помечает ВСЕ анонсы типа прочитанными этим тенантом —
     * после открытия раздела бейдж непрочитанного гаснет.
     *
     * @return array{data: list<array<string, mixed>>, current_page: int, last_page: int, total: int}
     */
    public function cabinetPaginated(AnnouncementType $type, string $tenantId): array
    {
        $page = $this->announcements->paginatePublishedOfType($type, self::PER_PAGE);
        $readIds = $this->announcements->readIdsForCurrentTenant();

        $result = $this->paginate($page, fn (Announcement $a): array => [
            'id' => $a->id,
            'title' => $a->title,
            'excerpt' => $this->excerpt($a->body),
            'published_at' => $a->published_at?->toDateTimeString(),
            'is_new' => ! in_array($a->id, $readIds, true),
        ]);

        $this->markRead($type, $tenantId);

        return $result;
    }

    /**
     * Детальная страница анонса для бизнеса (полный текст). Помечает прочитанным.
     *
     * @return array<string, mixed>|null
     */
    public function findForBusiness(string $id, AnnouncementType $type, string $tenantId): ?array
    {
        $announcement = $this->announcements->findPublished($id, $type);

        if ($announcement === null) {
            return null;
        }

        $this->announcements->markReadForCurrentTenant([$announcement->id], $tenantId);

        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'published_at' => $announcement->published_at?->toDateTimeString(),
        ];
    }

    /**
     * Непрочитанные новости для подсветки пункта меню.
     *
     * @return array{news: int}
     */
    public function unreadCounts(): array
    {
        return $this->announcements->unreadCountsForCurrentTenant();
    }

    /** Пометить все опубликованные анонсы типа прочитанными тенантом. */
    private function markRead(AnnouncementType $type, string $tenantId): void
    {
        $ids = $this->announcements->publishedOfType($type)->pluck('id')->all();
        $this->announcements->markReadForCurrentTenant($ids, $tenantId);
    }

    /**
     * @param  LengthAwarePaginator<int, Announcement>  $page
     * @param  callable(Announcement): array<string, mixed>  $map
     * @return array{data: list<array<string, mixed>>, current_page: int, last_page: int, total: int}
     */
    private function paginate(LengthAwarePaginator $page, callable $map): array
    {
        return [
            'data' => array_map($map, $page->items()),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ];
    }

    /** Короткая выжимка из HTML-текста для списка. */
    private function excerpt(string $html): string
    {
        $text = trim(html_entity_decode(strip_tags($html)));

        return Str::limit($text, 160);
    }

    /**
     * Лёгкая санитизация HTML от редактора (автор — доверенный СУ; это
     * защита от случайностей): убираем скрипты/стили, обработчики on* и
     * javascript:-ссылки.
     */
    private function sanitize(string $html): string
    {
        $html = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html);
        $html = (string) preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
        $html = (string) preg_replace('#(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2#i', '$1=$2#$2', $html);

        return trim($html);
    }
}
