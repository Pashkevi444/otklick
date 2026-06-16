<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\BotReply;
use App\DTO\IncomingMessage;
use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use App\Models\Channel;
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
        private ReplyComposer $composer,
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
     */
    public function reply(Channel $channel, string $token, string $text): BotReply
    {
        $sessionId = $this->sessionFromToken($channel, $token);

        $conversation = $this->conversations->firstOrCreateForChat($channel->id, $sessionId, 'Гость сайта');

        $this->messages->recordInbound($conversation, new IncomingMessage(
            externalChatId: $sessionId,
            externalMessageId: (string) Str::uuid(),
            text: $text,
        ));

        // Контакты клиента (телефон, имя) — до генерации ответа.
        $this->contacts->fromInbound($conversation, $text);

        $reply = $this->composer->compose($channel->tenant, $conversation);

        $this->messages->recordOutbound($conversation, $reply->text, MessageStatus::Sent);
        $this->conversations->touchLastMessage($conversation);

        if ($reply->escalate) {
            $this->conversations->updateStatus($conversation, ConversationStatus::NeedsHuman);
        }

        return $reply;
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
