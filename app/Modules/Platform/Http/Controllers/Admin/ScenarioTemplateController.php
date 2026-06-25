<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Flows\Models\ScenarioTemplate;
use App\Shared\Models\BusinessType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Управление готовыми шаблонами сценариев-воронок супер-админом. Шаблоны
 * глобальные (без tenant_id) — бизнес берёт готовый и правит под себя. Граф
 * `definition` редактируется как JSON (тот же формат, что в конструкторе кабинета);
 * начальный набор засеян миграцией `create_template_tables`.
 */
final class ScenarioTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/ScenarioTemplates/Index', [
            'templates' => ScenarioTemplate::query()
                ->orderBy('sort_order')
                ->get()
                ->map(fn (ScenarioTemplate $t): array => [
                    'id' => $t->id,
                    'key' => $t->key,
                    'name' => $t->name,
                    'description' => $t->description,
                    'business_type' => $t->business_type,
                    'triggers' => $t->triggers,
                    'definition' => $t->definition,
                    'sort_order' => $t->sort_order,
                ])
                ->all(),
            'businessTypes' => BusinessType::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ScenarioTemplate::create($this->validated($request, null));

        return back()->with('success', 'Шаблон сценария добавлен.');
    }

    public function update(Request $request, ScenarioTemplate $template): RedirectResponse
    {
        $template->update($this->validated($request, $template));

        return back()->with('success', 'Шаблон сценария обновлён.');
    }

    public function destroy(ScenarioTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('success', 'Шаблон сценария удалён.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ScenarioTemplate $template): array
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:120', 'alpha_dash', Rule::unique('scenario_templates', 'key')->ignore($template?->id)],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'business_type' => ['nullable', Rule::exists('business_types', 'key')],
            'triggers' => ['array'],
            'triggers.*' => ['string', 'max:120'],
            'definition' => ['required', 'array'],
            'definition.start' => ['required', 'string', 'max:120'],
            'definition.nodes' => ['required', 'array', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $data['description'] ??= '';
        $data['triggers'] = array_values($data['triggers'] ?? []);

        return $data;
    }
}
