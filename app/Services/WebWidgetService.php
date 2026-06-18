<?php

declare(strict_types=1);

namespace App\Services;

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
use App\Repositories\Contracts\MessageRepositoryInterface;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Диалог веб-виджета (чат на сайте бизнеса). Каждый посетитель получает
 * подписанный токен сессии (шифр на APP_KEY), который привязан к каналу и
 * конкретному диалогу — чужую переписку по нему не прочитать и не подделать.
 *
 * Вызывается строго в тенант-контексте (его ставит контроллер).
 */
final readonly class WebWidgetService
{
    public function __construct(
        private ConversationRepositoryInterface $conversations,
        private MessageRepositoryInterface $messages,
        private BotResponder $responder,
        private ContactCapture $contacts,
    ) {}

    /**
     * Начинает сессию: выдаёт подписанный токен с новым id сессии. Сам диалог в
     * БД НЕ создаётся — он появится лениво при первом реальном сообщении
     * (`reply`), чтобы открытие виджета без переписки не засоряло базу.
     */
    public function startSession(Channel $channel): string
    {
        $sessionId = (string) Str::uuid();

        return Crypt::encryptString($channel->id.'|'.$sessionId);
    }

    /**
     * Принимает сообщение посетителя и возвращает ответ бота по базе знаний.
     * $clientIp сохраняется в деталях диалога (аналог ссылки на аккаунт в мессенджерах).
     */
    public function reply(Channel $channel, string $token, string $text, ?string $clientIp = null): BotReply
    {
        $sessionId = $this->sessionFromToken($channel, $token);

        // contactName=null: имя поставит ContactCapture/ContactGate, а у
        // вернувшегося посетителя оно перенесётся из прошлого диалога (раньше
        // жёсткое «Гость сайта» затирало перенесённое имя).
        $conversation = $this->conversations->firstOrCreateForChat($channel->id, $sessionId, null, $clientIp);

        $this->messages->recordInbound($conversation, new IncomingMessage(
            externalChatId: $sessionId,
            externalMessageId: (string) Str::uuid(),
            text: $text,
        ));

        // Контакты клиента (телефон, имя) — до генерации ответа.
        $this->contacts->fromInbound($conversation, $text);

        $reply = $this->responder->respond($channel->tenant, $conversation, $text);

        $this->messages->recordOutbound($conversation, $reply->text, MessageStatus::Sent);
        $this->conversations->touchLastMessage($conversation);

        if ($reply->escalate) {
            $this->conversations->updateStatus($conversation, ConversationStatus::NeedsHuman);
        } elseif ($reply->booked) {
            // Запись оформлена (лид «в работе» до визита) — фиксируем + обновляем
            // резюме клиента (как в Telegram-пути).
            $this->conversations->markBooked($conversation);
            if ($conversation->client_id !== null) {
                RefreshClientSummary::dispatch((string) $channel->tenant_id, (string) $conversation->client_id);
            }
        } elseif ($reply->cancelled) {
            // Клиент отменил запись — отменяем в CRM и закрываем диалог.
            $this->responder->cancelBookingInCrm($conversation);
            $this->conversations->markCancelled($conversation);
        }

        $this->notifyOwner($channel, $conversation, $text, $reply);

        return $reply;
    }

    /**
     * Уведомляет владельца о событии в веб-виджете (в фоне).
     */
    private function notifyOwner(Channel $channel, Conversation $conversation, string $snippet, BotReply $reply): void
    {
        $tenantId = $channel->getAttribute('tenant_id');
        if (! is_string($tenantId) || $tenantId === '') {
            return;
        }

        $event = match (true) {
            $reply->escalate => OwnerEvent::NeedsHuman,
            $reply->booked => OwnerEvent::Booked,
            $reply->cancelled => OwnerEvent::Cancelled,
            $conversation->wasRecentlyCreated => OwnerEvent::NewLead,
            default => null,
        };

        if ($event === null) {
            return;
        }

        SendOwnerNotification::dispatch($tenantId, $event->value, [
            'contact' => $conversation->contact_name ?? 'Гость сайта',
            'phone' => (string) $conversation->contact_phone,
            'channel' => $channel->type->label(),
            'snippet' => $snippet,
            'conversationId' => $conversation->id,
        ]);
    }

    /**
     * Разбирает токен сессии и проверяет привязку к каналу.
     */
    private function sessionFromToken(Channel $channel, string $token): string
    {
        try {
            $payload = Crypt::decryptString($token);
        } catch (DecryptException) {
            abort(Response::HTTP_FORBIDDEN, 'Недействительная сессия.');
        }

        [$channelId, $sessionId] = array_pad(explode('|', $payload, 2), 2, '');

        abort_unless($channelId === $channel->id && $sessionId !== '', Response::HTTP_FORBIDDEN, 'Недействительная сессия.');

        return $sessionId;
    }
}
