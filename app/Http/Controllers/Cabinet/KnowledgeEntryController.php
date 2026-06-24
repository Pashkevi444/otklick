<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\DTO\KnowledgeEntryData;
use App\Enums\ChannelType;
use App\Enums\MemberPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cabinet\ImportSiteRequest;
use App\Http\Requests\Cabinet\KnowledgeEntryRequest;
use App\Jobs\ImportKnowledgeFromSite;
use App\Jobs\IndexKnowledge;
use App\Models\BusinessType;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeGap;
use App\Models\KnowledgeTemplate;
use App\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Repositories\Contracts\KnowledgeGapRepositoryInterface;
use App\Services\GapDraftStatus;
use App\Services\KnowledgeBaseService;
use App\Services\SiteImportStatus;
use App\Support\KnowledgeImageStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * База знаний тенанта в кабинете: текст, ссылки и картинки-«примеры работ».
 * Данные скоупятся текущим тенантом.
 */
final class KnowledgeEntryController extends Controller
{
    public function __construct(
        private readonly KnowledgeBaseService $knowledge,
        private readonly KnowledgeEntryRepositoryInterface $entries,
        private readonly KnowledgeImageStorage $images,
        private readonly KnowledgeGapRepositoryInterface $gaps,
    ) {}

    public function index(Request $request): Response
    {
        $tenantType = $request->user()->tenant->business_type;

        // Пагинация списка записей (на тенанта их немного — режем коллекцию
        // в памяти).
        $perPage = 9;
        $allEntries = $this->knowledge->list();
        $page = max(1, (int) $request->query('page', '1'));
        $lastPage = max(1, (int) ceil($allEntries->count() / $perPage));
        $page = min($page, $lastPage);

        // «Развитие бота» — вопросы клиентов, на которые бот не нашёл ответ
        // (отдельный таб). Бизнес видит их и дополняет базу знаний.
        return Inertia::render('Cabinet/KnowledgeBase/Index', [
            'entries' => $allEntries->forPage($page, $perPage)->map($this->present(...))->values()->all(),
            'pagination' => [
                'current' => $page,
                'last' => $lastPage,
                'total' => $allEntries->count(),
            ],
            'gaps' => $this->gaps->openForCurrentTenant()->map($this->presentGap(...))->all(),
            // Готовые элементы базы знаний — добавляются в один клик, бизнес дозаполняет
            // конкретику. Источник — `knowledge_templates` (СУ редактирует в админке).
            // Показываем только «Общие» + нишу самого тенанта (`tenants.business_type`).
            'templates' => KnowledgeTemplate::query()
                ->where(function ($q) use ($tenantType): void {
                    $q->whereNull('business_type');
                    if ($tenantType !== null) {
                        $q->orWhere('business_type', $tenantType);
                    }
                })
                ->orderBy('sort_order')
                ->get()
                ->map(fn (KnowledgeTemplate $t): array => [
                    'key' => $t->key,
                    'title' => $t->title,
                    'content' => $t->content,
                    'businessType' => $t->business_type,
                ])->all(),
            'businessTypes' => BusinessType::options(),
        ]);
    }

    public function store(KnowledgeEntryRequest $request): RedirectResponse
    {
        $this->authorizeEdit($request);

        $uploaded = $this->images->store($this->tenantId($request), $request->file('images', []));

        $this->knowledge->create($this->data($request, $uploaded));

        IndexKnowledge::dispatch($this->tenantId($request));

        return redirect()
            ->route('cabinet.knowledge.index')
            ->with('success', 'Запись добавлена.');
    }

    public function edit(string $entry, GapDraftStatus $draftStatus): Response
    {
        return Inertia::render('Cabinet/KnowledgeBase/Edit', [
            'entry' => $this->present($this->findOrFail($entry)),
            // Идёт ли фоновая генерация AI-черновика ответа (из «пробела бота»).
            'drafting' => $draftStatus->isDrafting($entry),
        ]);
    }

    /**
     * Статус фоновой генерации AI-черновика ответа + актуальный текст записи
     * (кабинет поллит, пока «пишется», затем подставляет готовый черновик).
     */
    public function draftStatus(string $entry, GapDraftStatus $status): JsonResponse
    {
        $model = $this->findOrFail($entry);

        return response()->json([
            'state' => $status->state($entry),
            'content' => (string) $model->content,
        ]);
    }

    public function update(KnowledgeEntryRequest $request, string $entry): RedirectResponse
    {
        $this->authorizeEdit($request);

        $model = $this->findOrFail($entry);

        $kept = array_values(array_filter(
            $model->images,
            fn (array $image): bool => in_array($image['path'], $request->input('existing_images', []), true),
        ));

        // Файлы, которые убрали из записи, удаляем с диска.
        $this->images->delete(array_values(array_diff(
            array_column($model->images, 'path'),
            array_column($kept, 'path'),
        )));

        $uploaded = $this->images->store($this->tenantId($request), $request->file('images', []));

        $this->knowledge->update($model, $this->data($request, [...$kept, ...$uploaded]));

        IndexKnowledge::dispatch($this->tenantId($request));

        return redirect()
            ->route('cabinet.knowledge.index')
            ->with('success', 'Запись обновлена.');
    }

    public function destroy(Request $request, string $entry): RedirectResponse
    {
        $this->authorizeEdit($request);

        $model = $this->findOrFail($entry);

        $this->images->delete(array_column($model->images, 'path'));
        $tenantId = (string) $model->tenant_id;
        $this->knowledge->delete($model);

        IndexKnowledge::dispatch($tenantId);

        return redirect()
            ->route('cabinet.knowledge.index')
            ->with('success', 'Запись удалена.');
    }

    /**
     * Запуск фонового импорта базы знаний с сайта бизнеса. Создаёт черновики —
     * бизнес сам решает, что опубликовать. Прогресс — в {@see SiteImportStatus}.
     */
    public function importSite(ImportSiteRequest $request, SiteImportStatus $status): RedirectResponse
    {
        $this->authorizeEdit($request);

        $tenantId = $this->tenantId($request);

        $status->begin($tenantId);
        ImportKnowledgeFromSite::dispatch($tenantId, (string) $request->string('url'));

        return redirect()
            ->route('cabinet.knowledge.index')
            ->with('success', 'Импорт с сайта запущен — записи появятся черновиками через минуту.');
    }

    /**
     * Прогресс импорта с сайта (для индикатора в кабинете).
     */
    public function importStatus(Request $request, SiteImportStatus $status): JsonResponse
    {
        return response()->json($status->get($this->tenantId($request)));
    }

    /**
     * Переключает публикацию записи прямо из списка (без открытия формы) —
     * удобно публиковать черновики, в т.ч. собранные импортом с сайта. Остальные
     * поля (ссылки/картинки) сохраняются как есть.
     */
    public function togglePublish(Request $request, string $entry): RedirectResponse
    {
        $this->authorizeEdit($request);

        $model = $this->findOrFail($entry);
        $willPublish = ! $model->is_published;

        $this->knowledge->update($model, new KnowledgeEntryData(
            title: $model->title,
            content: $model->content,
            isPublished: $willPublish,
            links: $model->links,
            images: $model->images,
        ));

        IndexKnowledge::dispatch((string) $model->tenant_id);

        return back()->with('success', $willPublish ? 'Запись опубликована.' : 'Запись снята с публикации.');
    }

    /**
     * Любое изменение базы знаний (создание/правка/удаление/публикация/импорт)
     * требует права-действия `knowledge.edit`. Доступ к разделу (просмотр) даёт
     * `EnsureSectionAllowed`; владелец/СУ имеют все права.
     */
    private function authorizeEdit(Request $request): void
    {
        abort_unless($request->user()->allows(MemberPermission::KnowledgeEdit->value), 403);
    }

    private function findOrFail(string $id): KnowledgeEntry
    {
        $entry = $this->entries->find($id);

        abort_if($entry === null, 404);

        return $entry;
    }

    private function tenantId(Request $request): string
    {
        return (string) $request->user()->tenant_id;
    }

    /**
     * @param  list<array{path: string, url: string}>  $images
     */
    private function data(KnowledgeEntryRequest $request, array $images): KnowledgeEntryData
    {
        $links = array_values(array_map(
            fn (array $link): array => [
                'label' => (string) $link['label'],
                'url' => (string) $link['url'],
            ],
            $request->input('links', []),
        ));

        return new KnowledgeEntryData(
            title: (string) $request->string('title'),
            content: (string) $request->string('content'),
            isPublished: $request->boolean('is_published'),
            links: $links,
            images: $images,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function present(KnowledgeEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'title' => $entry->title,
            'content' => $entry->content,
            'is_published' => $entry->is_published,
            'links' => $entry->links,
            'images' => $entry->images,
            'updated_at' => $entry->updated_at?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentGap(KnowledgeGap $gap): array
    {
        return [
            'id' => $gap->id,
            'question' => $gap->question,
            'occurrences' => $gap->occurrences,
            'channel' => $gap->channel_type !== null
                ? (ChannelType::tryFrom($gap->channel_type)?->label() ?? $gap->channel_type)
                : null,
            'conversation_id' => $gap->conversation_id,
            'last_seen_at' => $gap->last_seen_at?->toDateString(),
        ];
    }
}
