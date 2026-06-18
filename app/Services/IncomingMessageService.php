<?php

declare(strict_types=1);

namespace App\Services;

use App\Channels\ChannelGatewayResolver;
use App\DTO\BotReply;
use App\DTO\IncomingMessage;
use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use App\Enums\OwnerEvent;
use App\Jobs\RefreshClientSummary;
use App\Jobs\SendOwnerNotification;
use App\Models\Channel;
use App\Models\Conversation;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\KnowledgeGapRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Throwable;

/**
 * Обработка входящего сообщения: фиксация диалога и сообщения, генерация ответа
 * по базе знаний (ReplyComposer) и отправка обратно в канал.
 *
 * Если бот не знает ответа — отправляется вежливый фолбек, а диалог помечается
 * статусом «нужен человек».
 */
final readonly class IncomingMessageService
{
    public function __construct(
        private ConversationRepositoryInterface $conversations,
        private MessageRepositoryInterface $messages,
        private ChannelGatewayResolver $gateways,
        private BotResponder $responder,
        private ContactCapture $contacts,
        private KnowledgeGapRepositoryInterface $gaps,
    ) {}

    public function handle(Channel $channel, IncomingMessage $incoming): void
    {
        $conversation = $this->conversations->firstOrCreateForChat(
            $channel->id,
            $incoming->externalChatId,
            $incoming->contactName,
            $incoming->contactRef,
        );

        $inbound = $this->messages->recordInbound($conversation, $incoming);

        // Дубликат вебхука: сообщение уже обработано — выходим (идемпотентность).
        if ($inbound === null) {
            return;
        }

        // Контакты клиента (телефон, имя) — до генерации ответа.
        $this->contacts->fromInbound($conversation, $incoming->text);

        $reply = $this->responder->respond($channel->tenant, $conversation, $incoming->text);

        $status = MessageStatus::Sent;

        try {
            // Ответ уходит через шлюз того канала, откуда пришло сообщение
            // (Telegram/VK/…), а не через жёстко зашитый мессенджер.
            $this->gateways->for($channel->type)->send($channel, $incoming->externalChatId, $reply->text);
        } catch (Throwable $e) {
            $status = MessageStatus::Failed;
            report($e);
        }

        $this->messages->recordOutbound($conversation, $reply->text, $status);
        $this->conversations->touchLastMessage($conversation);

        if ($reply->escalate) {
            $this->conversations->updateStatus($conversation, ConversationStatus::NeedsHuman);

            // Бот не нашёл ответа в базе знаний — фиксируем вопрос в «пробелах
            // бота», чтобы бизнес дополнил базу (рост доверия → удержание).
            if ($reply->knowledgeGap) {
                $this->gaps->record($incoming->text, (string) $conversation->id, $channel->type->value);
            }
        } elseif ($reply->booked) {
            // Запись оформлена — закрываем диалог и фиксируем конверсию.
            $this->conversations->markBooked($conversation);

            // Обновляем резюме клиента по итогам записи (в фоне), если привязан.
            if ($conversation->client_id !== null) {
                RefreshClientSummary::dispatch((string) $channel->tenant_id, (string) $conversation->client_id);
            }
        } elseif ($reply->cancelled) {
            // Клиент отменил запись — отменяем в CRM и закрываем диалог.
            $this->responder->cancelBookingInCrm($conversation);
            $this->conversations->markCancelled($conversation);
        }

        $this->notifyOwner($channel, $conversation, $incoming->text, $reply);
    }

    /**
     * Уведомляет владельца о событии (в фоне). Событие выбирается по исходу
     * ответа; для нового диалога — «новый лид».
     */
    private function notifyOwner(Channel $channel, Conversation $conversation, string $snippet, BotReply $reply): void
    {
        $tenantId = $channel->getAttribute('tenant_id');
        if (! is_string($tenantId) || $tenantId === '') {
            return;
        }

        // Эскалацию операторам в Telegram выполняет живой мост (TelegramRelayService),
        // поэтому общий «нужен оператор» здесь не дублируем.
        if ($reply->escalate) {
            return;
        }

        $event = match (true) {
            $reply->booked => OwnerEvent::Booked,
            $reply->cancelled => OwnerEvent::Cancelled,
            $conversation->wasRecentlyCreated => OwnerEvent::NewLead,
            default => null,
        };

        if ($event === null) {
            return;
        }

        SendOwnerNotification::dispatch($tenantId, $event->value, [
            'contact' => $conversation->contact_name ?? 'Гость',
            'phone' => (string) $conversation->contact_phone,
            'channel' => $channel->type->label(),
            'snippet' => $snippet,
            'conversationId' => $conversation->id,
        ]);
    }
}
