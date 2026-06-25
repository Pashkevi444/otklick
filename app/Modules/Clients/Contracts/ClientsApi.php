<?php

declare(strict_types=1);

namespace App\Modules\Clients\Contracts;

use App\Modules\Clients\ClientsApiService;
use App\Modules\Clients\Models\Client;
use App\Modules\Conversations\Models\Conversation;
use Illuminate\Support\Collection;

/**
 * Публичный контракт модуля «Клиенты» — единственная дверь для других модулей.
 * Снаружи доступны только эти методы; ClientService/ClientRepository/джобы —
 * приватная кухня модуля. Реализация — {@see ClientsApiService}.
 */
interface ClientsApi
{
    /** Привязать/узнать клиента диалога по нативной идентичности канала. */
    public function attachClient(Conversation $conversation): void;

    public function recordName(Conversation $conversation, string $name): void;

    public function recordPhone(Conversation $conversation, string $phone): void;

    public function recordEmail(Conversation $conversation, string $email): void;

    /**
     * Аудитория рассылки (клиенты тенанта без marketing_opt_out).
     *
     * @param  list<string>|null  $clientIds
     * @return Collection<int, Client>
     */
    public function marketingAudienceForCurrentTenant(?array $clientIds = null): Collection;

    public function marketingAudienceCountForCurrentTenant(): int;

    /**
     * Список клиентов для пикера рассылки.
     *
     * @return list<array<string, mixed>>
     */
    public function pickerListForCurrentTenant(): array;

    /** Пересобрать LLM-резюме клиента в фоне (после успешной записи). */
    public function refreshSummaryAsync(string $tenantId, string $clientId): void;
}
