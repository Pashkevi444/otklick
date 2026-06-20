<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Models\Flow;
use App\Services\FlowService;
use App\Services\FlowSimulator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function index(): Response
    {
        $stats = $this->service->abStats();

        return Inertia::render('Cabinet/Scenarios/Index', [
            'flows' => $this->service->forCurrentTenant()->map(fn (Flow $f): array => $this->present($f, $stats[$f->id] ?? []))->all(),
            'actionOptions' => [
                ['value' => 'none', 'label' => 'Нет (показать кнопки/сообщение)'],
                ['value' => 'start_booking', 'label' => 'Начать запись в YClients'],
                ['value' => 'escalate', 'label' => 'Позвать администратора'],
                ['value' => 'end', 'label' => 'Завершить сценарий'],
            ],
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
