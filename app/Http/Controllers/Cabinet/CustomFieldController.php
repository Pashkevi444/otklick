<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\DTO\CustomFieldDefData;
use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Http\Controllers\Controller;
use App\Models\CustomFieldDef;
use App\Repositories\Contracts\CustomFieldDefRepositoryInterface;
use App\Services\CustomFieldService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Управление определениями кастомных полей (лиды/сделки). Сами значения правятся
 * в карточках лида/сделки; здесь — схема полей. За CRM (тариф `crm`); правка
 * полей сущности требует права-действия `leads.edit`/`deals.edit`.
 */
final class CustomFieldController extends Controller
{
    public function __construct(
        private readonly CustomFieldService $service,
        private readonly CustomFieldDefRepositoryInterface $defs,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entity' => ['required', Rule::enum(CustomFieldEntity::class)],
            'label' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::enum(CustomFieldType::class)],
            'options' => ['array'],
            'options.*' => ['string', 'max:60'],
        ]);

        $entity = CustomFieldEntity::from($data['entity']);
        $type = CustomFieldType::from($data['type']);
        $this->authorizeEntity($request, $entity);

        if ($type->hasOptions() && empty(array_filter($data['options'] ?? [], fn ($o): bool => trim((string) $o) !== ''))) {
            return back()->withErrors(['options' => 'Укажите хотя бы один вариант списка.']);
        }

        $this->service->createDef(new CustomFieldDefData(
            entity: $entity,
            label: $data['label'],
            type: $type,
            options: $data['options'] ?? null,
        ));

        return back()->with('success', 'Поле добавлено.');
    }

    public function update(Request $request, string $field): RedirectResponse
    {
        $def = $this->findOrFail($field);
        $this->authorizeEntity($request, $def->entity);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'options' => ['array'],
            'options.*' => ['string', 'max:60'],
        ]);

        $this->service->updateDef($def, $data['label'], $data['options'] ?? null);

        return back()->with('success', 'Поле обновлено.');
    }

    public function destroy(Request $request, string $field): RedirectResponse
    {
        $def = $this->findOrFail($field);
        $this->authorizeEntity($request, $def->entity);

        $this->service->deleteDef($def);

        return back()->with('success', 'Поле удалено.');
    }

    private function authorizeEntity(Request $request, CustomFieldEntity $entity): void
    {
        abort_unless($request->user()->allows($entity->editPermission()), 403);
    }

    private function findOrFail(string $id): CustomFieldDef
    {
        $def = $this->defs->find($id);

        abort_if($def === null, 404);

        return $def;
    }
}
