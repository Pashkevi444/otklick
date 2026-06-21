<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Models\Flow;
use App\Models\KnowledgeEntry;
use App\Repositories\Contracts\CrmConnectionRepositoryInterface;
use App\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Services\FlowService;
use App\Services\FlowSimulator;
use App\Support\KnowledgeImageStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Конструктор сценариев-воронок (no-code логика бота). Граф узлов/переходов
 * приходит из редактора как `definition`. Гейт `plan:flows` + раздел `scenarios`.
 */
final class FlowController extends Controller
{
    public function __construct(
        private readonly FlowService $service,
        private readonly FlowSimulator $simulator,
        private readonly CrmConnectionRepositoryInterface $crm,
        private readonly KnowledgeEntryRepositoryInterface $knowledge,
    ) {}

    /** Сухой прогон воронки для теста в кабинете (без побочных эффектов). */
    public function test(Request $request): JsonResponse
    {
        $data = $request->validate([
            'definition' => ['required', 'array'],
            'state' => ['nullable', 'array'],
            'text' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var array{node: ?string, vars?: array<string, mixed>}|null $state */
        $state = $data['state'] ?? null;

        return response()->json($this->simulator->step((array) $data['definition'], $state, $data['text'] ?? null));
    }

    /**
     * Загрузка одной картинки для узла сценария. Возвращает {path, url} — фронт
     * кладёт это в `images` узла (тот же формат, что в базе знаний). Сохранение
     * самого сценария идёт обычным PUT/POST с definition (URL уже внутри).
     */
    public function image(Request $request, KnowledgeImageStorage $storage): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'max:5120']]);

        /** @var UploadedFile $file */
        $file = $request->file('image');
        $stored = $storage->store((string) $request->user()->tenant_id, [$file], 'flows');

        return response()->json($stored[0]);
    }

    public function index(): Response
    {
        $stats = $this->service->abStats();

        $yclientsActive = $this->crm->activeForCurrentTenant() !== null;

        return Inertia::render('Cabinet/Scenarios/Index', [
            'flows' => $this->service->forCurrentTenant()->map(fn (Flow $f): array => $this->present($f, $stats[$f->id] ?? []))->all(),
            // Действие «Начать запись в YClients» показываем только если CRM
            // подключена (иначе кнопка запустила бы мастер, которого нет).
            'yclientsActive' => $yclientsActive,
            // Элементы базы знаний для действия «Показать элемент базы знаний».
            'knowledgeEntries' => $this->knowledge->forCurrentTenant()
                ->map(fn (KnowledgeEntry $e): array => ['id' => $e->id, 'title' => $e->title])
                ->values()
                ->all(),
            'actionOptions' => array_values(array_filter([
                ['value' => 'none', 'label' => 'Нет (показать кнопки/сообщение)'],
                $yclientsActive ? ['value' => 'start_booking', 'label' => 'Начать запись в YClients'] : null,
                ['value' => 'show_knowledge', 'label' => 'Показать элемент базы знаний'],
                ['value' => 'escalate', 'label' => 'Позвать администратора'],
                ['value' => 'end', 'label' => 'Завершить сценарий'],
            ])),
            'nodeTypeOptions' => [
                ['value' => 'message', 'label' => 'Сообщение (текст + кнопки)'],
                ['value' => 'input', 'label' => 'Вопрос (сохранить ответ в переменную)'],
                ['value' => 'condition', 'label' => 'Условие (ветвление по переменной)'],
                ['value' => 'split', 'label' => 'A/B-сплит (поделить трафик)'],
            ],
            'operatorOptions' => [
                ['value' => 'eq', 'label' => 'равно'],
                ['value' => 'neq', 'label' => 'не равно'],
                ['value' => 'contains', 'label' => 'содержит'],
                ['value' => 'filled', 'label' => 'заполнена'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->service->create((string) $request->user()->tenant_id, $this->validated($request));

        return redirect()->route('cabinet.scenarios.index')->with('success', 'Сценарий создан.');
    }

    public function update(Request $request, string $flow): RedirectResponse
    {
        $model = $this->findOrFail($flow);

        $this->service->update($model, $this->validated($request));

        return redirect()->route('cabinet.scenarios.index')->with('success', 'Сценарий сохранён.');
    }

    public function toggle(string $flow): RedirectResponse
    {
        $this->service->toggle($this->findOrFail($flow));

        return back();
    }

    public function destroy(string $flow): RedirectResponse
    {
        $this->service->delete($this->findOrFail($flow));

        return redirect()->route('cabinet.scenarios.index')->with('success', 'Сценарий удалён.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['boolean'],
            'triggers' => ['array', 'max:20'],
            'triggers.*' => ['string', 'max:80'],
            'definition' => ['array'],
            'definition.start' => ['nullable', 'string'],
            'definition.nodes' => ['array'],
        ]);

        return [
            'name' => (string) $validated['name'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'triggers' => array_values(array_filter(array_map(
                static fn ($t): string => trim((string) $t),
                (array) ($validated['triggers'] ?? []),
            ), static fn (string $t): bool => $t !== '')),
            'definition' => (array) ($validated['definition'] ?? []),
        ];
    }

    private function findOrFail(string $id): Flow
    {
        $flow = $this->service->find($id);

        abort_if($flow === null, 404);

        return $flow;
    }

    /**
     * @param  list<array{variant: string, total: int, booked: int, conversion: float}>  $ab
     * @return array<string, mixed>
     */
    private function present(Flow $flow, array $ab): array
    {
        return [
            'id' => $flow->id,
            'name' => $flow->name,
            'is_active' => $flow->is_active,
            'triggers' => $flow->triggers,
            'definition' => $flow->definition,
            'ab' => $ab,
        ];
    }
}
