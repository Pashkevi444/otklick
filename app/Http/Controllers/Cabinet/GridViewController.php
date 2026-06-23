<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\DTO\GridViewData;
use App\Enums\GridEntity;
use App\Http\Controllers\Controller;
use App\Models\GridView;
use App\Repositories\Contracts\GridViewRepositoryInterface;
use App\Services\GridViewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Личные сохранённые виды универсального грида (колонки/фильтры/сортировка).
 * Каждый пользователь управляет только своими видами; данные изолированы тенантом
 * (RLS) и `user_id`.
 */
final class GridViewController extends Controller
{
    public function __construct(
        private readonly GridViewService $service,
        private readonly GridViewRepositoryInterface $views,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, withEntity: true);

        $this->service->create(new GridViewData(
            entity: GridEntity::from($data['entity']),
            name: $data['name'],
            config: $data['config'],
            userId: (int) $request->user()->id,
        ));

        return back()->with('success', 'Вид сохранён.');
    }

    public function update(Request $request, string $view): RedirectResponse
    {
        $model = $this->ownedOrFail($request, $view);
        $data = $this->validated($request, withEntity: false);

        $this->service->update($model, $data['name'], $data['config']);

        return back()->with('success', 'Вид обновлён.');
    }

    public function destroy(Request $request, string $view): RedirectResponse
    {
        $this->service->delete($this->ownedOrFail($request, $view));

        return back()->with('success', 'Вид удалён.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $withEntity): array
    {
        return $request->validate([
            'entity' => [Rule::requiredIf($withEntity), Rule::enum(GridEntity::class)],
            'name' => ['required', 'string', 'max:60'],
            'config' => ['required', 'array'],
            'config.columns' => ['array'],
            'config.columns.*' => ['string', 'max:64'],
            'config.filters' => ['array'],
            'config.filters.*.field' => ['required', 'string', 'max:64'],
            'config.filters.*.op' => ['required', 'string', 'max:16'],
            'config.filters.*.value' => ['nullable'],
            'config.sort' => ['nullable', 'array'],
            'config.sort.field' => ['required_with:config.sort', 'string', 'max:64'],
            'config.sort.dir' => ['nullable', 'in:asc,desc'],
        ]);
    }

    private function ownedOrFail(Request $request, string $id): GridView
    {
        $view = $this->views->find($id);

        abort_if($view === null, 404);
        abort_unless((int) $view->user_id === (int) $request->user()->id, 403);

        return $view;
    }
}
