<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\DTO\KnowledgeEntryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cabinet\KnowledgeEntryRequest;
use App\Models\KnowledgeEntry;
use App\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Services\KnowledgeBaseService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * База знаний тенанта в кабинете. Данные скоупятся текущим тенантом.
 */
final class KnowledgeEntryController extends Controller
{
    public function __construct(
        private readonly KnowledgeBaseService $knowledge,
        private readonly KnowledgeEntryRepositoryInterface $entries,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Cabinet/KnowledgeBase/Index', [
            'entries' => $this->knowledge->list()->map($this->present(...))->all(),
        ]);
    }

    public function store(KnowledgeEntryRequest $request): RedirectResponse
    {
        $this->knowledge->create($this->data($request));

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
        $this->knowledge->update($this->findOrFail($entry), $this->data($request));

        return redirect()
            ->route('cabinet.knowledge.index')
            ->with('success', 'Запись обновлена.');
    }

    public function destroy(string $entry): RedirectResponse
    {
        $this->knowledge->delete($this->findOrFail($entry));

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

    private function data(KnowledgeEntryRequest $request): KnowledgeEntryData
    {
        return new KnowledgeEntryData(
            title: (string) $request->string('title'),
            content: (string) $request->string('content'),
            isPublished: $request->boolean('is_published'),
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
            'updated_at' => $entry->updated_at?->toDateString(),
        ];
    }
}
