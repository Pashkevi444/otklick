<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTO\IncomingMessage;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Collection;

/**
 * Контракт доступа к данным сообщений.
 */
interface MessageRepositoryInterface
{
    /**
     * Последние сообщения диалога в хронологическом порядке (для истории в LLM).
     *
     * @return Collection<int, Message>
     */
    public function recentForConversation(Conversation $conversation, int $limit): Collection;

    /**
     * Последние сообщения ЧАТА (по каналу и external_chat_id) через все его
     * диалоги — чтобы бот помнил прошлое общение (напр. оформленную запись) даже
     * после закрытия диалога и старта нового. В хронологическом порядке.
     *
     * @return Collection<int, Message>
     */
    public function recentForChat(string $channelId, string $externalChatId, int $limit): Collection;

    /**
     * Все сообщения диалога в хронологическом порядке (для журнала переписок).
     *
     * @return Collection<int, Message>
     */
    public function allForConversation(Conversation $conversation): Collection;

    /**
     * Сообщения диалога, появившиеся ПОСЛЕ сообщения с id $afterId (лайв-поллинг
     * кабинета и виджета). $afterId=null/'' — все сообщения. Курсор — id (UUIDv7,
     * монотонный по времени).
     *
     * @return Collection<int, Message>
     */
    public function sinceForConversation(Conversation $conversation, ?string $afterId): Collection;

    /**
     * Сохраняет входящее сообщение. Возвращает null, если сообщение с таким
     * external_message_id уже записано в этом диалоге (идемпотентность ретраев).
     */
    public function recordInbound(Conversation $conversation, IncomingMessage $incoming): ?Message;

    /**
     * Сохраняет исходящее сообщение. $images — список URL картинок (ответ оператора
     * с фото), кладётся в `payload.images` — как у входящих фото клиента.
     *
     * @param  list<string>  $images
     */
    public function recordOutbound(Conversation $conversation, string $text, MessageStatus $status, array $images = []): Message;

    /**
     * Обновляет статус доставки сообщения по id (для фоновой повторной отправки:
     * queued → sent при успехе или failed, когда ретраи исчерпаны).
     */
    public function markStatusById(string $messageId, MessageStatus $status): void;

    /**
     * Текст последней реплики бота в диалоге (для контекста — например, чтобы
     * понять, спрашивал ли бот имя). null, если бот ещё ничего не отправлял.
     */
    public function latestOutboundText(Conversation $conversation): ?string;
}
