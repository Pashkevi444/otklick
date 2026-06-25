<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Jobs;

use App\Modules\Knowledge\Services\SiteImportStatus;
use App\Modules\Knowledge\Services\WebsiteKnowledgeImportService;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Фоновый импорт базы знаний с сайта бизнеса. По кнопке в кабинете задача
 * вешается на очередь, тянет страницы сайта и создаёт черновики записей. Прогресс
 * пишем в {@see SiteImportStatus}, кабинет его опрашивает. Импорт может занять
 * минуту — поэтому не в HTTP-запросе.
 */
final class ImportKnowledgeFromSite implements ShouldQueue
{
    use Dispatchable, Queueable;

    /** Импорт долгий (сеть + LLM на несколько страниц) — даём запас по времени. */
    public int $timeout = 300;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $url,
    ) {}

    public function handle(
        TenantInitializer $tenancy,
        WebsiteKnowledgeImportService $import,
        SiteImportStatus $status,
    ): void {
        $status->begin($this->tenantId);

        try {
            $created = $tenancy->run(
                $this->tenantId,
                fn (): int => $import->import(
                    $this->url,
                    fn (int $percent, int $created) => $status->report($this->tenantId, $percent, $created),
                ),
            );

            $status->succeed($this->tenantId, $created);
        } catch (Throwable $e) {
            $status->fail($this->tenantId);

            throw $e;
        }
    }
}
