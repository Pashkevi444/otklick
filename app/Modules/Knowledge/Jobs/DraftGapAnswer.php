<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Jobs;

use App\Modules\Knowledge\DTO\KnowledgeEntryData;
use App\Modules\Knowledge\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Modules\Knowledge\Services\GapDraftStatus;
use App\Modules\Knowledge\Services\KnowledgeGapDrafter;
use App\Shared\Models\Tenant;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Фоновая генерация AI-черновика ответа для записи БЗ, созданной из «пробела бота».
 * Пишет статус в {@see GapDraftStatus} (кабинет показывает индикатор и поллит).
 * Черновик строится на данных бизнеса + нишевом промпте ({@see KnowledgeGapDrafter});
 * при сбое LLM в записи останется пустой текст (владелец впишет сам).
 */
final class DraftGapAnswer implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $entryId,
        public readonly string $question,
    ) {}

    public function handle(
        TenantInitializer $tenancy,
        KnowledgeGapDrafter $drafter,
        KnowledgeEntryRepositoryInterface $entries,
        GapDraftStatus $status,
    ): void {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            $status->finish($this->entryId);

            return;
        }

        $tenancy->run($this->tenantId, function () use ($tenant, $drafter, $entries, $status): void {
            $entry = $entries->find($this->entryId);

            if ($entry !== null) {
                $entries->update($entry, new KnowledgeEntryData(
                    title: (string) $entry->title,
                    content: $drafter->draft($tenant, $this->question),
                    isPublished: (bool) $entry->is_published,
                    links: $entry->links ?? [],
                    images: $entry->images ?? [],
                ));
            }

            $status->finish($this->entryId);
        });
    }
}
