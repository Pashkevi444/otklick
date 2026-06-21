<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BusinessType;
use App\Http\Controllers\Controller;
use App\Models\KnowledgeTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Управление готовыми шаблонами базы знаний супер-админом. Шаблоны глобальные
 * (без tenant_id) — бизнес добавляет их в свою базу знаний в один клик. Начальный
 * набор засеян миграцией `create_template_tables`; здесь СУ правит/добавляет/
 * удаляет без выката кода.
 */
final class KnowledgeTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/KnowledgeTemplates/Index', [
            'templates' => KnowledgeTemplate::query()
                ->orderBy('sort_order')
                ->get()
                ->map(fn (KnowledgeTemplate $t): array => [
                    'id' => $t->id,
                    'key' => $t->key,
                    'title' => $t->title,
                    'content' => $t->content,
                    'business_type' => $t->business_type,
                    'sort_order' => $t->sort_order,
                ])
                ->all(),
            'businessTypes' => self::businessTypes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        KnowledgeTemplate::create($this->validated($request, null));

        return back()->with('success', 'Шаблон базы знаний добавлен.');
    }

    public function update(Request $request, KnowledgeTemplate $template): RedirectResponse
    {
        $template->update($this->validated($request, $template));

        return back()->with('success', 'Шаблон базы знаний обновлён.');
    }

    public function destroy(KnowledgeTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('success', 'Шаблон базы знаний удалён.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?KnowledgeTemplate $template): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:120', 'alpha_dash', Rule::unique('knowledge_templates', 'key')->ignore($template?->id)],
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'max:20000'],
            'business_type' => ['nullable', Rule::in(array_column(BusinessType::cases(), 'value'))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function businessTypes(): array
    {
        return array_map(
            fn (BusinessType $t): array => ['value' => $t->value, 'label' => $t->label()],
            BusinessType::cases(),
        );
    }
}
