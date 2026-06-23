<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\DTO\DealData;
use App\Enums\CrmSource;
use App\Enums\CustomFieldEntity;
use App\Enums\GridEntity;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\DealStage;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\DealRepositoryInterface;
use App\Repositories\Contracts\DealStageRepositoryInterface;
use App\Services\CustomFieldService;
use App\Services\DealService;
use App\Services\GridViewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRM-воронка сделок (канбан) в кабинете. Данные скоупятся текущим тенантом.
 */
final class DealController extends Controller
{
    public function __construct(
        private readonly DealService $service,
        private readonly DealRepositoryInterface $deals,
        private readonly DealStageRepositoryInterface $stages,
        private readonly ClientRepositoryInterface $clients,
        private readonly CustomFieldService $fields,
        private readonly GridViewService $views,
    ) {}

    public function index(Request $request): Response
    {
        $this->service->ensureStages();

        return Inertia::render('Cabinet/Deals/Index', [
            'stages' => $this->stages->forCurrentTenant()->map(fn (DealStage $s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'kind' => $s->kind->value,
            ])->all(),
            'deals' => $this->deals->forCurrentTenant()->map($this->present(...))->all(),
            'clients' => $this->clients->pickerListForCurrentTenant(),
            'team' => $request->user()->tenant->users()->orderBy('name')->get(['id', 'name'])
                ->map(fn ($u): array => ['id' => $u->id, 'name' => $u->name])->all(),
            'fields' => $this->fields->present(CustomFieldEntity::Deal),
            'views' => $this->views->present((int) $request->user()->id, GridEntity::Deal),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeEdit($request);
        $data = $this->validated($request);

        $this->service->create(new DealData(
            stageId: $data['stage_id'],
            clientId: $data['client_id'] ?? null,
            title: $data['title'] ?? null,
            value: isset($data['value']) ? (int) $data['value'] : null,
            assignedUserId: isset($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null,
            source: CrmSource::Manual,
            notes: $data['notes'] ?? null,
            custom: $this->fields->sanitize(CustomFieldEntity::Deal, $request->input('custom', [])),
        ));

        return back()->with('success', 'Сделка добавлена.');
    }

    public function update(Request $request, string $deal): RedirectResponse
    {
        $this->authorizeEdit($request);
        $model = $this->findOrFail($deal);

        // Перенос между стадиями (drag-n-drop) — лёгкий путь.
        if ($request->has('stage_id') && ! $request->hasAny(['title', 'value', 'notes', 'assigned_user_id', 'client_id'])) {
            $request->validate(['stage_id' => ['required', 'string', $this->stageRule()]]);
            $this->service->moveToStage($model, (string) $request->string('stage_id'));

            return back();
        }

        $data = $this->validated($request);
        $this->deals->update($model, [
            'stage_id' => $data['stage_id'],
            'client_id' => $data['client_id'] ?? null,
            'title' => $data['title'] ?? null,
            'value' => isset($data['value']) ? (int) $data['value'] : null,
            'assigned_user_id' => isset($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null,
            'notes' => $data['notes'] ?? null,
            'custom' => $this->fields->sanitize(CustomFieldEntity::Deal, $request->input('custom', [])),
        ]);

        return back()->with('success', 'Сделка обновлена.');
    }

    public function destroy(Request $request, string $deal): RedirectResponse
    {
        $this->authorizeEdit($request);
        $this->deals->delete($this->findOrFail($deal));

        return back()->with('success', 'Сделка удалена.');
    }

    /** Изменение сделок требует права-действия `deals.edit`. */
    private function authorizeEdit(Request $request): void
    {
        abort_unless($request->user()->allows('deals.edit'), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'stage_id' => ['required', 'string', $this->stageRule()],
            'client_id' => ['nullable', 'string', 'exists:clients,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'value' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function stageRule(): \Closure
    {
        return function (string $attr, mixed $value, \Closure $fail): void {
            if (! is_string($value) || $this->stages->find($value) === null) {
                $fail('Стадия не найдена.');
            }
        };
    }

    private function findOrFail(string $id): Deal
    {
        $deal = $this->deals->find($id);

        abort_if($deal === null, 404);

        return $deal;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Deal $deal): array
    {
        return [
            'id' => $deal->id,
            'title' => $deal->title,
            'value' => $deal->value,
            'stage_id' => $deal->stage_id,
            'source' => $deal->source->value,
            'notes' => $deal->notes,
            'client' => $deal->client !== null ? ['id' => $deal->client->id, 'name' => $deal->client->name, 'phone' => $deal->client->phone] : null,
            'client_id' => $deal->client_id,
            'assigned' => $deal->assignedUser !== null ? ['id' => $deal->assignedUser->id, 'name' => $deal->assignedUser->name] : null,
            'assigned_user_id' => $deal->assigned_user_id,
            'custom' => $deal->custom ?? new \stdClass,
            'created_at' => $deal->created_at?->toDateString(),
        ];
    }
}
