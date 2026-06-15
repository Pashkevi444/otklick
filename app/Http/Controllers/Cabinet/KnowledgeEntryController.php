<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\DTO\KnowledgeEntryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cabinet\KnowledgeEntryRequest;
use App\Models\KnowledgeEntry;
use App\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Services\KnowledgeBaseService;
use App\Support\KnowledgeImageStorage;
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
    ) {}

    public function index(): Response
    {
        return Inertia::render('Cabinet/KnowledgeBase/Index', [
            'entries' => $this->knowledge->list()->map($this->present(...))->all(),
        ]);
    }

    public function store(KnowledgeEntryRequest $request): RedirectResponse
    {
        $uploaded = $this->images->store($this->tenantId($request), $request->file('images', []));

        $this->knowledge->create($this->data($request, $uploaded));

        return redirect()
            ->route('cabinet.knowledge.index')
            ->with('success', 'Запись добавлена.');
    }

    public function edit(string $entry): Response
    {
        return Inertia::render('Cabinet/KnowledgeBase/Edit', [
            'entry' => $this->present($this->findOrFail($entry)),
        ]);
    }

    public function update(KnowledgeEntryRequest $request, string $entry): RedirectResponse
    {
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

        return redirect()
            ->route('cabinet.knowledge.index')
            ->with('success', 'Запись обновлена.');
    }

    public function destroy(string $entry): RedirectResponse
    {
        $model = $this->findOrFail($entry);

        $this->images->delete(array_column($model->images, 'path'));
        $this->knowledge->delete($model);

        return redirect()
            ->route('cabinet.knowledge.index')
            ->with('success', 'Запись удалена.');
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
}
