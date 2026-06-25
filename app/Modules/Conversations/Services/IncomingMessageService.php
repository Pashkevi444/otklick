<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Services;

use App\Modules\Bot\Services\BotResponder;
use App\Modules\Channels\ChannelGatewayResolver;
use App\Modules\Channels\Jobs\DeliverBotReply;
use App\Modules\Channels\Models\Channel;
use App\Modules\Clients\Jobs\RefreshClientSummary;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Repositories\Contracts\ConversationRepositoryInterface;
use App\Modules\Conversations\Repositories\Contracts\MessageRepositoryInterface;
use App\Modules\Knowledge\Repositories\Contracts\KnowledgeGapRepositoryInterface;
use App\Modules\Notifications\Jobs\SendOwnerNotification;
use App\Modules\Notifications\Services\UserNotificationService;
use App\Shared\DTO\BotReply;
use App\Shared\DTO\IncomingMessage;
use App\Shared\Enums\ConversationOutcome;
use App\Shared\Enums\ConversationStatus;
use App\Shared\Enums\MessageStatus;
use App\Shared\Enums\OwnerEvent;
use App\Shared\Enums\UserNotificationType;
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
        private SpamDetector $spam,
        private UserNotificationService $notifications,
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

        // Диалог перехвачен оператором — бот молчит; входящее увидит оператор в
        // кабинете и ответит сам.
        if ($conversation->isOperatorHandling()) {
            $this->conversations->touchLastMessage($conversation);

            return;
        }

        // Диалог уже эскалирован (ждёт оператора) — бот молчит, чтобы не повторять
        // «передал администратору» на каждое сообщение (даже смайлик); клиента
        // подхватит оператор. (В Telegram это делает мост; здесь — для VK/MAX/WhatsApp.)
        if ($conversation->status === ConversationStatus::NeedsHuman) {
            $this->conversations->touchLastMessage($conversation);

            return;
        }

        // Контакты клиента (телефон, имя) — до генерации ответа. Здесь же
        // резолвится карточка клиента по нативной идентичности канала.
        $this->contacts->fromInbound($conversation, $incoming->text);

        if ($conversation->client?->isBanned()) {
            // Забаненному клиенту бот отвечает фиксированным уведомлением (без LLM).
            $reply = new BotReply($channel->tenant->banNotice(), escalate: false);
        } elseif ($this->spam->isSpam($conversation, $incoming->text)) {
            // Явный спам — молчим (не тратим LLM), помечаем диалог как спам.
            $this->conversations->setOutcome($conversation, ConversationOutcome::Spam);
            $this->conversations->touchLastMessage($conversation);

            return;
        } else {
            $reply = $this->responder->respond($channel->tenant, $conversation, $incoming->text);
        }

        try {
            // Ответ уходит через шлюз того канала, откуда пришло сообщение
            // (Telegram/VK/…), а не через жёстко зашитый мессенджер.
            $this->gateways->for($channel->type)->send($channel, $incoming->externalChatId, $reply->text, $reply->keyboard, $reply->images);
            $this->messages->recordOutbound($conversation, $reply->text, MessageStatus::Sent);
        } catch (Throwable $e) {
            // Отправка сорвалась — НЕ теряем реплай и НЕ оставляем диалог висеть:
            // фиксируем как «в очереди» и добиваем фоновым ретраем с бэкоффом
            // (если канал так и не оживёт — DeliverBotReply уведёт диалог на человека).
            report($e);
            $outbound = $this->messages->recordOutbound($conversation, $reply->text, MessageStatus::Queued);
            DeliverBotReply::dispatch(
                (string) $channel->tenant_id,
                $channel->id,
                $incoming->externalChatId,
                $reply->text,
                $reply->keyboard,
                (string) $outbound->id,
                (string) $conversation->id,
                $reply->images,
            );
        }

        $this->conversations->touchLastMessage($conversation);

        // Ссылка на диалог в кабинете — лениво (нужна только при отправке уведомления;
        // строим из имени роута, не хардкодом — поменяется URI, ссылка не протухнет).
        $convUrl = fn (): string => route('cabinet.conversations.show', $conversation->id, false);

        if ($reply->escalate) {
            $this->conversations->updateStatus($conversation, ConversationStatus::NeedsHuman);

            $this->notifications->notify(
                UserNotificationType::Escalation,
                'Диалог требует администратора',
                $this->snippet($incoming->text),
                $convUrl(),
                'conversation',
                (string) $conversation->id,
            );

            // Бот не нашёл ответа в базе знаний — фиксируем вопрос в «пробелах
            // бота», чтобы бизнес дополнил базу (рост доверия → удержание).
            if ($reply->knowledgeGap) {
                $gap = $this->gaps->record($incoming->text, (string) $conversation->id, $channel->type->value);

                $this->notifications->notify(
                    UserNotificationType::KnowledgeGap,
                    'Вопрос без ответа',
                    $this->snippet($incoming->text),
                    route('cabinet.knowledge.index', absolute: false),
                    'gap',
                    (string) $gap->id,
                );
            }
        } elseif ($reply->booked) {
            // Запись оформлена — закрываем диалог и фиксируем конверсию.
            $this->conversations->markBooked($conversation);

            $this->notifications->notify(
                UserNotificationType::Booked,
                'Запись оформлена',
                $conversation->displayName() ?? 'Гость',
                $convUrl(),
                'conversation',
                (string) $conversation->id,
            );

            // Обновляем резюме клиента по итогам записи (в фоне), если привязан.
            if ($conversation->client_id !== null) {
                RefreshClientSummary::dispatch((string) $channel->tenant_id, (string) $conversation->client_id);
            }
        } elseif ($reply->cancelled) {
            // Клиент отменил запись — отменяем в CRM и закрываем диалог.
            $this->responder->cancelBookingInCrm($conversation);
            $this->conversations->markCancelled($conversation);
        } elseif ($conversation->wasRecentlyCreated) {
            // Новый диалог без записи/эскалации — это новый лид.
            $this->notifications->notify(
                UserNotificationType::NewLead,
                'Новый лид',
                $conversation->displayName() ?? $this->snippet($incoming->text),
                $convUrl(),
                'conversation',
                (string) $conversation->id,
            );
        }

        $this->notifyOwner($channel, $conversation, $incoming->text, $reply);
    }

    /** Короткая выжимка текста клиента для тела уведомления. */
    private function snippet(string $text): string
    {
        return mb_substr(trim($text), 0, 160);
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

        // Ссылка на аккаунт клиента — только настоящий URL мессенджера (VK/Telegram),
        // а не IP веб-виджета: чтобы владелец мог написать клиенту в его канал.
        $profile = (string) $conversation->contact_ref;
        $profile = str_starts_with($profile, 'http') ? $profile : '';

        SendOwnerNotification::dispatch($tenantId, $event->value, [
            'contact' => $conversation->displayName() ?? 'Гость',
            'phone' => (string) $conversation->displayPhone(),
            'channel' => $channel->type->label(),
            'profile' => $profile,
            'snippet' => $snippet,
            'conversationId' => $conversation->id,
        ]);
    }
}
