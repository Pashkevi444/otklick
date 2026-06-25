<?php

declare(strict_types=1);

namespace App\Modules\Booking\Jobs;

use App\Modules\Channels\Contracts\MessengerGateway;
use App\Modules\Conversations\Contracts\ConversationsApi;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Отправка одного напоминания клиенту о записи (через очередь Horizon).
 * Планировщик решает, КОГДА напомнить, и ставит эту задачу; здесь — сама
 * отправка (ретраится при сбое канала). «Застолбил» напоминание планировщик,
 * так что дублей между тиками нет.
 */
final class SendAppointmentReminder implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $conversationId,
        public readonly string $text,
    ) {}

    public function handle(
        TenantInitializer $tenancy,
        ConversationsApi $conversations,
        MessengerGateway $gateway,
    ): void {
        $tenancy->run($this->tenantId, function () use ($conversations, $gateway): void {
            $conversation = $conversations->findForCurrentTenant($this->conversationId);

            if ($conversation === null || $conversation->channel === null) {
                return;
            }

            $gateway->send($conversation->channel, $conversation->external_chat_id, $this->text);
        });
    }
}
