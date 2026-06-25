<?php

declare(strict_types=1);

namespace App\Modules\Clients;

use App\Modules\Clients\Contracts\ClientsApi;
use App\Modules\Clients\Jobs\RefreshClientSummary;
use App\Modules\Clients\Repositories\Contracts\ClientRepositoryInterface;
use App\Modules\Clients\Services\ClientService;
use App\Modules\Conversations\Models\Conversation;
use Illuminate\Support\Collection;

/**
 * Фасад модуля «Клиенты»: реализует {@see ClientsApi}, делегируя внутренним
 * сервису/репозиторию/джобе. Имена методов совпадают с внутренними — потребители
 * меняют только тип в конструкторе.
 */
final class ClientsApiService implements ClientsApi
{
    public function __construct(
        private readonly ClientService $service,
        private readonly ClientRepositoryInterface $clients,
    ) {}

    public function attachClient(Conversation $conversation): void
    {
        $this->service->attachClient($conversation);
    }

    public function recordName(Conversation $conversation, string $name): void
    {
        $this->service->recordName($conversation, $name);
    }

    public function recordPhone(Conversation $conversation, string $phone): void
    {
        $this->service->recordPhone($conversation, $phone);
    }

    public function recordEmail(Conversation $conversation, string $email): void
    {
        $this->service->recordEmail($conversation, $email);
    }

    public function marketingAudienceForCurrentTenant(?array $clientIds = null): Collection
    {
        return $this->clients->marketingAudienceForCurrentTenant($clientIds);
    }

    public function marketingAudienceCountForCurrentTenant(): int
    {
        return $this->clients->marketingAudienceCountForCurrentTenant();
    }

    public function pickerListForCurrentTenant(): array
    {
        return $this->clients->pickerListForCurrentTenant();
    }

    public function refreshSummaryAsync(string $tenantId, string $clientId): void
    {
        RefreshClientSummary::dispatch($tenantId, $clientId);
    }
}
