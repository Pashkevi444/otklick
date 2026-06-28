<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Services;

use App\Modules\Bot\Contracts\BotApi;
use App\Modules\Channels\Models\Channel;
use App\Modules\Channels\Services\ImageRecognitionService;
use App\Modules\Clients\Jobs\RefreshClientSummary;
use App\Modules\Conversations\Events\ClientTyping;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Conversations\Repositories\Contracts\ConversationRepositoryInterface;
use App\Modules\Conversations\Repositories\Contracts\MessageRepositoryInterface;
use App\Modules\Notifications\Contracts\NotificationsApi;
use App\Modules\Notifications\Jobs\SendOwnerNotification;
use App\Shared\DTO\BotReply;
use App\Shared\DTO\IncomingMessage;
use App\Shared\Enums\ConversationOutcome;
use App\Shared\Enums\ConversationStatus;
use App\Shared\Enums\MessageDirection;
use App\Shared\Enums\MessageStatus;
use App\Shared\Enums\OwnerEvent;
use App\Shared\Enums\UserNotificationType;
use App\Shared\Support\ImageMime;
use App\Shared\Support\WidgetRealtimeChannel;
use App\Shared\Vision\Contracts\ImageToText;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
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
        private BotApi $responder,
        private ContactCapture $contacts,
        private SpamDetector $spam,
        private ImageToText $vision,
        private NotificationsApi $notifications,
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
     * Принимает сообщение посетителя и возвращает ответ бота по базе знаний +
     * курсор `lastId` (id последнего сообщения — с него виджет продолжит поллинг,
     * чтобы не задвоить уже показанный ответ). $clientIp сохраняется в деталях
     * диалога (аналог ссылки на аккаунт в мессенджерах).
     *
     * @return array{reply: BotReply, lastId: string}
     */
    public function reply(Channel $channel, string $token, string $text, ?string $clientIp = null, bool $consent = false): array
    {
        $sessionId = $this->sessionFromToken($channel, $token);

        // contactName=null: имя поставит ContactCapture/ContactGate, а у
        // вернувшегося посетителя оно перенесётся из прошлого диалога (раньше
        // жёсткое «Гость сайта» затирало перенесённое имя).
        $conversation = $this->conversations->firstOrCreateForChat($channel->id, $sessionId, null, $clientIp);

        // Согласие на обработку ПД виджет даёт галочкой — фиксируем его (152-ФЗ),
        // тогда ConsentGate в боте пропускает диалог дальше без формы Да/Нет.
        if ($consent) {
            $this->conversations->markConsentGiven($conversation);
        }

        $inbound = $this->messages->recordInbound($conversation, new IncomingMessage(
            externalChatId: $sessionId,
            externalMessageId: (string) Str::uuid(),
            text: $text,
        ));

        // Контакты клиента (телефон, имя) — до генерации ответа.
        $this->contacts->fromInbound($conversation, $text);

        // Диалог перехвачен оператором — бот молчит; сообщение посетителя увидит
        // оператор в кабинете и ответит сам (виджет получит ответ поллингом).
        if ($conversation->isOperatorHandling()) {
            $this->conversations->touchLastMessage($conversation);

            return ['reply' => new BotReply('', escalate: false), 'lastId' => (string) $inbound->id];
        }

        // Диалог эскалирован (ждёт оператора), но оператор НЕ перехватил
        // (isOperatorHandling — ветка выше): бот продолжает отвечать на вопросы
        // посетителя, помечая, что оператор уже подключён. Перехватит — замолчит.
        if ($conversation->status === ConversationStatus::NeedsHuman) {
            $answer = $this->responder->respond($channel->tenant, $conversation, $text);
            // Несём картинки/клавиатуру ответа (напр. «примеры работ») — иначе фото терялись.
            $reply = new BotReply(BotReply::ESCALATED_NOTE."\n\n".$answer->text, escalate: false, keyboard: $answer->keyboard, images: $answer->images);
            $outbound = $this->messages->recordOutbound($conversation, $reply->text, MessageStatus::Sent);
            $this->conversations->touchLastMessage($conversation);

            return ['reply' => $reply, 'lastId' => (string) $outbound->id];
        }

        // Забаненный посетитель — фиксированное уведомление без LLM.
        if ($conversation->client?->isBanned()) {
            $reply = new BotReply($channel->tenant->banNotice(), escalate: false);
            $outbound = $this->messages->recordOutbound($conversation, $reply->text, MessageStatus::Sent);
            $this->conversations->touchLastMessage($conversation);

            return ['reply' => $reply, 'lastId' => (string) $outbound->id];
        }

        // Явный спам — молчим (без LLM), помечаем диалог как спам.
        if ($this->spam->isSpam($conversation, $text)) {
            $this->conversations->setOutcome($conversation, ConversationOutcome::Spam);
            $this->conversations->touchLastMessage($conversation);

            return ['reply' => new BotReply('', escalate: false), 'lastId' => (string) $inbound->id];
        }

        $reply = $this->responder->respond($channel->tenant, $conversation, $text);

        $outbound = $this->messages->recordOutbound($conversation, $reply->text, MessageStatus::Sent);
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

        $this->notifyCabinet($conversation, $reply, $text);
        $this->notifyOwner($channel, $conversation, $text, $reply);

        return ['reply' => $reply, 'lastId' => (string) $outbound->id];
    }

    /**
     * Клиент прислал фото в виджете. Бот распознаёт картинку через vision-модель
     * (описание подставляется как «сообщение клиента» — бот отвечает по базе
     * знаний, как на текст). Если распознавание выключено/не удалось — фолбэк на
     * прежнее поведение: фото уходит администратору (диалог → needs_human).
     *
     * @param  list<array{path: string, url: string}>  $images  уже сохранённые на диск файлы
     * @return array{reply: BotReply, lastId: string, images: list<string>, operatorActive: bool}
     */
    public function receiveImage(Channel $channel, string $token, array $images, string $caption, ?string $clientIp = null, bool $consent = false): array
    {
        $sessionId = $this->sessionFromToken($channel, $token);
        $conversation = $this->conversations->firstOrCreateForChat($channel->id, $sessionId, null, $clientIp);

        if ($consent) {
            $this->conversations->markConsentGiven($conversation);
        }

        $urls = array_map(static fn (array $i): string => $i['url'], $images);

        $inbound = $this->messages->recordInbound($conversation, new IncomingMessage(
            externalChatId: $sessionId,
            externalMessageId: (string) Str::uuid(),
            text: $caption,
            raw: ['images' => $images],
        ));
        $inboundId = $inbound !== null ? (string) $inbound->id : '';

        if ($caption !== '') {
            $this->contacts->fromInbound($conversation, $caption);
        }

        // Оператор уже ведёт диалог ИЛИ диалог уже эскалирован (ждёт оператора) —
        // просто сохраняем фото без повторного «передали администратору».
        if ($conversation->isOperatorHandling() || $conversation->status === ConversationStatus::NeedsHuman) {
            $this->conversations->touchLastMessage($conversation);

            return ['reply' => new BotReply('', escalate: false), 'lastId' => $inboundId, 'images' => $urls, 'operatorActive' => $conversation->isOperatorHandling()];
        }

        // Пытаемся «увидеть» фото: описание становится вводом клиента, бот отвечает
        // как на обычный текст. Не распозналось — передаём администратору (фолбэк).
        $recognized = $this->describeImages($images, $caption);

        if ($recognized !== null) {
            $reply = $this->responder->respond($channel->tenant, $conversation, $recognized);

            $outbound = $this->messages->recordOutbound($conversation, $reply->text, MessageStatus::Sent);
            $this->conversations->touchLastMessage($conversation);

            if ($reply->escalate) {
                $this->conversations->updateStatus($conversation, ConversationStatus::NeedsHuman);
            } elseif ($reply->booked) {
                $this->conversations->markBooked($conversation);
                if ($conversation->client_id !== null) {
                    RefreshClientSummary::dispatch((string) $channel->tenant_id, (string) $conversation->client_id);
                }
            } elseif ($reply->cancelled) {
                $this->responder->cancelBookingInCrm($conversation);
                $this->conversations->markCancelled($conversation);
            }

            $this->notifyCabinet($conversation, $reply, $caption !== '' ? $caption : '📷 Фото от клиента');
            $this->notifyOwner($channel, $conversation, $caption !== '' ? $caption : '📷 Фото от клиента', $reply);

            return ['reply' => $reply, 'lastId' => (string) $outbound->id, 'images' => $urls, 'operatorActive' => false];
        }

        // Бот не «увидел» картинку — честно передаём администратору.
        $ack = 'Спасибо! Получили ваше фото и передали администратору — он скоро ответит.';
        $reply = new BotReply($ack, escalate: true);
        $outbound = $this->messages->recordOutbound($conversation, $ack, MessageStatus::Sent);
        $this->conversations->updateStatus($conversation, ConversationStatus::NeedsHuman);
        $this->conversations->touchLastMessage($conversation);

        $this->notifyCabinet($conversation, $reply, $caption !== '' ? $caption : '📷 Фото от клиента');
        $this->notifyOwner($channel, $conversation, $caption !== '' ? $caption : '📷 Фото от клиента', $reply);

        return ['reply' => $reply, 'lastId' => (string) $outbound->id, 'images' => $urls, 'operatorActive' => false];
    }

    /**
     * Прогоняет сохранённые фото через vision-порт и складывает ввод клиента
     * (подпись + описание). null — vision выключен/не распознал ни одной картинки.
     *
     * @param  list<array{path: string, url: string}>  $images
     */
    private function describeImages(array $images, string $caption): ?string
    {
        $descriptions = [];

        foreach ($images as $image) {
            if (! Storage::disk('public')->exists($image['path'])) {
                continue;
            }

            $bytes = (string) Storage::disk('public')->get($image['path']);
            if ($bytes === '') {
                continue;
            }

            $description = $this->vision->describe($bytes, ImageMime::sniff($bytes), $caption);
            if ($description !== null && trim($description) !== '') {
                $descriptions[] = trim($description);
            }
        }

        if ($descriptions === []) {
            return null;
        }

        return ImageRecognitionService::compose($caption, $descriptions);
    }

    /**
     * Лайв-поллинг виджета: исходящие сообщения диалога (ответы бота И оператора),
     * появившиеся после $afterId, + признак того, что сейчас на связи оператор
     * (виджет покажет баннер). Диалога ещё нет (сессия без переписки) → пусто.
     *
     * @return array{messages: list<array{id: string, text: string}>, operatorActive: bool}
     */
    public function poll(Channel $channel, string $token, ?string $afterId): array
    {
        $sessionId = $this->sessionFromToken($channel, $token);
        $conversation = $this->conversations->findActiveForChat($channel->id, $sessionId);

        if ($conversation === null) {
            return ['messages' => [], 'operatorActive' => false];
        }

        $messages = $this->messages->sinceForConversation($conversation, $afterId)
            ->filter(fn (Message $m): bool => $m->direction === MessageDirection::Outbound
                && (trim((string) $m->text) !== '' || $this->messageImages($m) !== []))
            ->map(fn (Message $m): array => [
                'id' => (string) $m->id,
                'text' => (string) $m->text,
                // Картинки ответа оператора (виджет рисует их как <img>).
                'images' => $this->messageImages($m),
            ])
            ->values()
            ->all();

        return ['messages' => $messages, 'operatorActive' => $conversation->isOperatorHandling()];
    }

    /**
     * URL картинок сообщения из `payload.images` ({path, url} или строки). Пусто —
     * текстовое сообщение.
     *
     * @return list<string>
     */
    private function messageImages(Message $m): array
    {
        $images = $m->payload['images'] ?? null;

        if (! is_array($images)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($img): ?string => is_array($img)
                ? (is_string($img['url'] ?? null) ? $img['url'] : null)
                : (is_string($img) ? $img : null),
            $images,
        )));
    }

    /**
     * Имя публичного реалтайм-канала сессии (виджет подписывается на него, чтобы
     * показать «оператор печатает»). Выводится из канала и id сессии.
     */
    public function realtimeChannel(Channel $channel, string $token): string
    {
        return WidgetRealtimeChannel::name($channel->id, $this->sessionFromToken($channel, $token));
    }

    /**
     * Посетитель печатает в виджете — шлём эфемерный сигнал в кабинет (на приватный
     * канал тенанта), чтобы открытый диалог показал «клиент печатает». Диалога ещё
     * нет (сессия без переписки) — тихо выходим.
     */
    public function markClientTyping(Channel $channel, string $token): void
    {
        $sessionId = $this->sessionFromToken($channel, $token);
        $conversation = $this->conversations->findActiveForChat($channel->id, $sessionId);

        if ($conversation === null) {
            return;
        }

        $tenantId = $channel->getAttribute('tenant_id');
        if (is_string($tenantId) && $tenantId !== '') {
            ClientTyping::dispatch($tenantId, (string) $conversation->id);
        }
    }

    /**
     * In-app уведомление в кабинет (колокольчик + бейджи) — как у мессенджеров в
     * {@see IncomingMessageService}. Раньше веб-виджет его НЕ создавал, поэтому
     * эскалации/лиды с сайта не попадали в колокол. Тип события — по исходу ответа.
     */
    private function notifyCabinet(Conversation $conversation, BotReply $reply, string $text): void
    {
        [$type, $title, $body] = match (true) {
            $reply->escalate => [UserNotificationType::Escalation, 'Диалог требует администратора', $this->snippet($text)],
            $reply->booked => [UserNotificationType::Booked, 'Запись оформлена', $conversation->displayName() ?? 'Гость сайта'],
            $conversation->wasRecentlyCreated => [UserNotificationType::NewLead, 'Новый лид', $conversation->displayName() ?? $this->snippet($text)],
            default => [null, '', ''],
        };

        if ($type === null) {
            return;
        }

        $url = route('cabinet.conversations.show', $conversation->id, false);
        $this->notifications->notify($type, $title, $body, $url, 'conversation', (string) $conversation->id);
    }

    /** Короткая выжимка текста клиента для тела уведомления. */
    private function snippet(string $text): string
    {
        return mb_substr(trim($text), 0, 160);
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
            'contact' => $conversation->displayName() ?? 'Гость сайта',
            'phone' => (string) $conversation->displayPhone(),
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
