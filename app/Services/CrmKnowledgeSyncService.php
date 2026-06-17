<?php

declare(strict_types=1);

namespace App\Services;

use App\Crm\CrmGatewayResolver;
use App\Crm\Data\CrmCompany;
use App\Crm\Data\CrmService;
use App\Crm\Data\CrmStaff;
use App\Enums\CrmKnowledgeCategory;
use App\Jobs\SyncCrmKnowledge;
use App\Repositories\Contracts\CrmConnectionRepositoryInterface;
use App\Repositories\Contracts\CrmKnowledgeRepositoryInterface;
use Illuminate\Support\Facades\Log;

/**
 * Выгрузка справочных данных из CRM (услуги/цены, мастера, филиал) в отдельную
 * нередактируемую базу знаний (`crm_knowledge_entries`). Слоты НЕ выгружаются —
 * они реал-тайм и берутся вживую при записи. Запускается фоновой задачей
 * {@see SyncCrmKnowledge} в текущем тенант-контексте.
 */
final readonly class CrmKnowledgeSyncService
{
    public function __construct(
        private CrmConnectionRepositoryInterface $connections,
        private CrmGatewayResolver $gateways,
        private CrmKnowledgeRepositoryInterface $knowledge,
    ) {}

    /**
     * @param  (callable(int): void)|null  $onProgress  колбэк прогресса 0–100% по этапам
     */
    public function sync(?callable $onProgress = null): void
    {
        $progress = static function (int $percent) use ($onProgress): void {
            if ($onProgress !== null) {
                $onProgress($percent);
            }
        };

        $connection = $this->connections->activeForCurrentTenant();

        if ($connection === null) {
            Log::warning('crm_knowledge.sync.no_connection');

            return;
        }

        $progress(10);
        $gateway = $this->gateways->for($connection->provider);

        $services = $gateway->services($connection);
        $progress(40);

        $staff = $gateway->staff($connection);
        $progress(65);

        $company = $gateway->company($connection);
        $progress(85);

        $rows = [
            ...array_map($this->serviceRow(...), $services),
            ...array_map($this->staffRow(...), $staff),
        ];

        if ($company instanceof CrmCompany) {
            $rows[] = $this->companyRow($company);
        }

        $this->knowledge->replaceForCurrentTenant($rows);
        $progress(100);

        Log::info('crm_knowledge.sync.done', [
            'provider' => $connection->provider->value,
            'services' => count($services),
            'staff' => count($staff),
            'company' => $company instanceof CrmCompany,
            'total' => count($rows),
        ]);
    }

    /**
     * @return array{category: string, external_id: string|null, title: string, content: string, meta: array<string, mixed>|null}
     */
    private function serviceRow(CrmService $service): array
    {
        $content = $service->title;
        if ($service->price !== null) {
            $content .= " — {$service->price} ₽";
        }
        if ($service->durationMinutes !== null) {
            $content .= ", ~{$service->durationMinutes} мин";
        }

        return [
            'category' => CrmKnowledgeCategory::Service->value,
            'external_id' => $service->id,
            'title' => $service->title,
            'content' => $content,
            'meta' => ['price' => $service->price, 'duration_minutes' => $service->durationMinutes],
        ];
    }

    /**
     * @return array{category: string, external_id: string|null, title: string, content: string, meta: array<string, mixed>|null}
     */
    private function staffRow(CrmStaff $staff): array
    {
        $content = $staff->name;
        if ($staff->specialization !== null && $staff->specialization !== '') {
            $content .= " — {$staff->specialization}";
        }

        return [
            'category' => CrmKnowledgeCategory::Staff->value,
            'external_id' => $staff->id,
            'title' => $staff->name,
            'content' => $content,
            'meta' => ['specialization' => $staff->specialization],
        ];
    }

    /**
     * @return array{category: string, external_id: string|null, title: string, content: string, meta: array<string, mixed>|null}
     */
    private function companyRow(CrmCompany $company): array
    {
        $parts = array_filter([$company->title, $company->address, $company->phone]);

        return [
            'category' => CrmKnowledgeCategory::Company->value,
            'external_id' => null,
            'title' => $company->title,
            'content' => implode(', ', $parts),
            'meta' => ['address' => $company->address, 'phone' => $company->phone],
        ];
    }
}
