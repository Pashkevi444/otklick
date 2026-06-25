<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Http\Controllers;

use App\Modules\Knowledge\DTO\KnowledgeEntryData;
use App\Modules\Knowledge\Jobs\DraftGapAnswer;
use App\Modules\Knowledge\Models\KnowledgeGap;
use App\Modules\Knowledge\Repositories\Contracts\KnowledgeGapRepositoryInterface;
use App\Modules\Knowledge\Services\GapDraftStatus;
use App\Modules\Knowledge\Services\KnowledgeBaseService;
use App\Modules\Notifications\Contracts\NotificationsApi;
use App\Shared\Enums\KnowledgeGapStatus;
use App\Shared\Enums\MemberPermission;
use App\Shared\Http\Controller;
use App\Shared\Models\User;
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
        private readonly GapDraftStatus $draftStatus,
        private readonly NotificationsApi $notifications,
    ) {}

    /** Обработал пробел (в БЗ/скрыл/удалил) → его уведомление у пользователя гаснет. */
    private function markGapSeen(Request $request, KnowledgeGap $gap): void
    {
        if (($user = $request->user()) instanceof User) {
            $this->notifications->markEntityRead($user, 'gap', (string) $gap->id);
        }
    }

    /**
     * «В базу знаний»: создаёт черновик записи (заголовок = вопрос клиента),
     * запускает ФОНОВУЮ генерацию AI-черновика ответа (на данных бизнеса + нишевом
     * промпте) и закрывает пробел. Кабинет открывает запись и показывает индикатор
     * «AI пишет черновик…», пока джоба не допишет текст; владелец правит и публикует.
     */
    public function promote(Request $request, string $gap): RedirectResponse
    {
        $this->authorizeEdit($request);

        $model = $this->findOrFail($gap);
        $this->markGapSeen($request, $model);

        $entry = $this->knowledge->create(new KnowledgeEntryData(
            title: mb_substr(trim($model->question), 0, 200),
            content: '',
            isPublished: false,
        ));

        $this->draftStatus->begin((string) $entry->id);
        DraftGapAnswer::dispatch((string) $request->user()->tenant_id, (string) $entry->id, $model->question);

        $this->gaps->updateStatus($model, KnowledgeGapStatus::Resolved);

        return redirect()
            ->route('cabinet.knowledge.edit', $entry->id)
            ->with('success', 'Готовлю AI-черновик ответа — он появится через несколько секунд.');
    }

    public function dismiss(Request $request, string $gap): RedirectResponse
    {
        $this->authorizeEdit($request);

        $model = $this->findOrFail($gap);
        $this->markGapSeen($request, $model);
        $this->gaps->updateStatus($model, KnowledgeGapStatus::Dismissed);

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
