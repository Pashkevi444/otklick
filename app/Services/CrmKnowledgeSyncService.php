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

    public function sync(): void
    {
        $connection = $this->connections->activeForCurrentTenant();

        if ($connection === null) {
            Log::warning('crm_knowledge.sync.no_connection');

            return;
        }

        $gateway = $this->gateways->for($connection->provider);

        $services = $gateway->services($connection);
        $staff = $gateway->staff($connection);
        $company = $gateway->company($connection);

        $rows = [
            ...array_map($this->serviceRow(...), $services),
            ...array_map($this->staffRow(...), $staff),
        ];

        if ($company instanceof CrmCompany) {
            $rows[] = $this->companyRow($company);
        }

        $this->knowledge->replaceForCurrentTenant($rows);

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
