<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PipelineEvent;
use App\Models\Conversation;
use App\Repositories\Contracts\DealRepositoryInterface;
use App\Repositories\Contracts\DealStageRepositoryInterface;
use App\Repositories\Contracts\LeadRepositoryInterface;

/**
 * Авто-движок воронки: связывает события диалога (бронь/эскалация/отмена/успех) со
 * сделкой. По событию гарантирует, что лид сконвертирован в сделку, и двигает её
 * в стадию с соответствующей automation-ролью.
 *
 * Лид (а значит и сделка) появляется только когда клиент отдал контакты
 * (создаётся в ContactGate). Событие до контактов — лида нет, авто-движок молчит.
 *
 * Не final — мокается в юнит-тестах вызывающих сервисов (IncomingMessageService).
 */
class DealAutomationService
{
    public function __construct(
        private readonly LeadRepositoryInterface $leads,
        private readonly LeadService $leadService,
        private readonly DealRepositoryInterface $deals,
        private readonly DealStageRepositoryInterface $stages,
    ) {}

    public function onEvent(Conversation $conversation, PipelineEvent $event): void
    {
        $lead = $this->leads->findByConversation((string) $conversation->id);
        if ($lead === null) {
            return; // контакты ещё не отданы — лида нет, воронка не стартовала
        }

        $deal = $this->leadService->convertToDeal($lead);
        if ($deal === null) {
            return;
        }

        $stage = $this->stages->firstByAutomation($event->targetAutomation());
        if ($stage !== null && $stage->id !== $deal->stage_id) {
            $this->deals->update($deal, ['stage_id' => $stage->id]);
        }
    }
}
