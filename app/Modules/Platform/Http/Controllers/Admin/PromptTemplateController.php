<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Bot\Models\PromptTemplate;
use App\Modules\Bot\Services\PromptBuilder;
use App\Shared\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Управление промптами бота под ниши (business_type) супер-админом. В БД хранится
 * только НАСТРАИВАЕМАЯ «голова» системного промпта; стандартный «хвост» (сентинелы
 * записи/эскалации + блоки данных) собирает {@see PromptBuilder} в коде.
 *
 * Записи засеяны миграцией под все ниши; здесь СУ правит тело без выката кода.
 * Переменные в теле — `{{...}}` (см. {@see PromptBuilder::VARIABLES}).
 */
final class PromptTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = PromptTemplate::query()
            ->orderBy('sort_order')
            ->paginate(12)
            ->through(fn (PromptTemplate $t): array => [
                'id' => $t->id,
                'business_type' => $t->business_type,
                'name' => $t->name,
                'body' => $t->body,
            ]);

        return Inertia::render('Admin/PromptTemplates/Index', [
            'templates' => $templates,
            'variables' => array_map(
                static fn (string $desc, string $key): array => ['token' => '{{'.$key.'}}', 'desc' => $desc],
                PromptBuilder::VARIABLES,
                array_keys(PromptBuilder::VARIABLES),
            ),
        ]);
    }

    public function update(Request $request, PromptTemplate $template): RedirectResponse
    {
        $template->update($request->validate([
            'name' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
        ]));

        return back()->with('success', 'Промпт обновлён.');
    }
}
