<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\DealData;
use App\DTO\LeadData;
use App\Enums\CrmSource;
use App\Enums\LeadStatus;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Lead;
use App\Repositories\Contracts\DealRepositoryInterface;
use App\Repositories\Contracts\LeadRepositoryInterface;

/**
 * Лиды — входящие обращения. Создаются по факту контактной формы (из диалога) или
 * вручную. Конвертируются в сделку (создаёт сделку в воронке и связывает).
 *
 * Не final — мокается в юнит-тестах вызывающих сервисов (ContactGate).
 */
class LeadService
{
    public function __construct(
        private readonly LeadRepositoryInterface $leads,
        private readonly DealService $deals,
        private readonly DealRepositoryInterface $dealRepo,
    ) {}

    /**
     * Создаёт лид из диалога по факту контактной формы. Идемпотентно: если у
     * диалога уже есть лид или ещё нет карточки клиента — ничего не делает.
     */
    public function createFromConversation(Conversation $conversation): ?Lead
    {
        if ($conversation->client_id === null) {
            return null;
        }
        if ($this->leads->existsForConversation((string) $conversation->id)) {
            return null;
        }

        return $this->leads->create(new LeadData(
            clientId: (string) $conversation->client_id,
            conversationId: (string) $conversation->id,
            source: CrmSource::Bot,
        ));
    }

    public function createManual(LeadData $data): Lead
    {
        return $this->leads->create($data);
    }

    /**
     * Конвертирует лид в сделку: создаёт сделку в первой стадии воронки и
     * связывает (`deal_id`), статус лида → «в сделке». Повторно — возвращает
     * уже созданную сделку.
     */
    public function convertToDeal(Lead $lead): ?Deal
    {
        if ($lead->deal_id !== null) {
            return $this->dealRepo->find((string) $lead->deal_id);
        }

        $stageId = $this->deals->firstStageId();
        if ($stageId === null) {
            return null;
        }

        $deal = $this->deals->create(new DealData(
            stageId: $stageId,
            clientId: $lead->client_id,
            title: $lead->title ?? $lead->client?->name,
            source: $lead->source,
            notes: $lead->notes,
        ));

        $this->leads->update($lead, ['deal_id' => $deal->id, 'status' => LeadStatus::Converted]);

        return $deal;
    }

    public function dismiss(Lead $lead): void
    {
        $this->leads->update($lead, ['status' => LeadStatus::Dismissed]);
    }
}
