<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Jobs\SyncCrmKnowledge;
use App\Models\CrmKnowledgeEntry;
use App\Repositories\Contracts\CrmConnectionRepositoryInterface;
use App\Repositories\Contracts\CrmKnowledgeRepositoryInterface;
use App\Services\CrmSyncStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Вкладка «База знаний из CRM» — нередактируемые данные (услуги, мастера,
 * филиал), выгруженные из системы записи фоновой задачей. Клиентская база
 * знаний (раздел «База знаний») при этом не трогается.
 */
final class CrmKnowledgeController extends Controller
{
    public function __construct(
        private readonly CrmKnowledgeRepositoryInterface $knowledge,
        private readonly CrmConnectionRepositoryInterface $connections,
        private readonly CrmSyncStatus $status,
    ) {}

    public function index(): Response
    {
        $entries = $this->knowledge->forCurrentTenant();

        return Inertia::render('Cabinet/Knowledge/Crm', [
            'connected' => $this->connections->activeForCurrentTenant() !== null,
            'lastSyncedAt' => $entries->max(fn (CrmKnowledgeEntry $e): ?string => $e->updated_at?->toIso8601String()),
            'groups' => $entries
                ->groupBy(fn (CrmKnowledgeEntry $e): string => $e->category->label())
                ->map(fn ($group): array => $group->map(fn (CrmKnowledgeEntry $e): array => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'content' => $e->content,
                ])->values()->all())
                ->all(),
        ]);
    }

    public function sync(): RedirectResponse
    {
        $tenantId = request()->user()->tenant_id;

        abort_if($this->connections->activeForCurrentTenant() === null, 422, 'Сначала подключите CRM.');

        $this->status->begin((string) $tenantId);
        SyncCrmKnowledge::dispatch((string) $tenantId);

        return redirect()
            ->route('cabinet.knowledge.crm')
            ->with('success', 'Загрузка данных из CRM запущена — записи появятся через минуту.');
    }

    /**
     * Прогресс фоновой выгрузки (для индикатора в %).
     */
    public function status(): JsonResponse
    {
        return response()->json($this->status->get((string) request()->user()->tenant_id));
    }
}
