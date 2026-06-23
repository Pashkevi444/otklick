<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\DTO\KnowledgeEntryData;
use App\Enums\KnowledgeGapStatus;
use App\Enums\MemberPermission;
use App\Http\Controllers\Controller;
use App\Models\KnowledgeGap;
use App\Repositories\Contracts\KnowledgeGapRepositoryInterface;
use App\Services\KnowledgeBaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Действия над «пробелами бота» (вопросами без ответа) на вкладке «Развитие бота».
 * Маршруты названы `cabinet.knowledge.gaps.*` — остаются в секции «knowledge»
 * (гейтинг разделов команды через EnsureSectionAllowed).
 */
final class KnowledgeGapController extends Controller
{
    public function __construct(
        private readonly KnowledgeGapRepositoryInterface $gaps,
        private readonly KnowledgeBaseService $knowledge,
    ) {}

    /**
     * «В базу знаний»: создаёт черновик записи (заголовок = вопрос клиента) и
     * закрывает пробел; владелец вписывает ответ и публикует.
     */
    public function promote(Request $request, string $gap): RedirectResponse
    {
        $this->authorizeEdit($request);

        $model = $this->findOrFail($gap);

        $entry = $this->knowledge->create(new KnowledgeEntryData(
            title: mb_substr(trim($model->question), 0, 200),
            content: '',
            isPublished: false,
        ));

        $this->gaps->updateStatus($model, KnowledgeGapStatus::Resolved);

        return redirect()
            ->route('cabinet.knowledge.edit', $entry->id)
            ->with('success', 'Создан черновик записи — впишите ответ и опубликуйте.');
    }

    public function dismiss(Request $request, string $gap): RedirectResponse
    {
        $this->authorizeEdit($request);

        $this->gaps->updateStatus($this->findOrFail($gap), KnowledgeGapStatus::Dismissed);

        return back()->with('success', 'Вопрос скрыт.');
    }

    public function destroy(Request $request, string $gap): RedirectResponse
    {
        $this->authorizeEdit($request);

        $this->gaps->delete($this->findOrFail($gap));

        return back()->with('success', 'Вопрос удалён.');
    }

    private function authorizeEdit(Request $request): void
    {
        abort_unless($request->user()->allows(MemberPermission::KnowledgeEdit->value), 403);
    }

    private function findOrFail(string $id): KnowledgeGap
    {
        $gap = $this->gaps->find($id);

        abort_if($gap === null, 404);

        return $gap;
    }
}
