<?php

declare(strict_types=1);

namespace App\Modules\Channels\Services;

use App\Modules\Channels\Models\Channel;
use App\Modules\Channels\Telegram\TelegramGateway;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Repositories\Contracts\ConversationRepositoryInterface;
use App\Modules\Conversations\Repositories\Contracts\MessageRepositoryInterface;
use App\Modules\Conversations\Services\ContactCapture;
use App\Modules\Notifications\Models\NotificationRecipient;
use App\Modules\Notifications\Repositories\Contracts\NotificationRecipientRepositoryInterface;
use App\Shared\DTO\IncomingMessage;
use App\Shared\Enums\ConversationStatus;
use App\Shared\Enums\MessageStatus;
use App\Shared\Enums\NotificationChannelType;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;

/**
 * Живой мост «оператор ↔ клиент» через бот бизнеса при эскалации.
 *
 * Пока диалог в статусе «нужен человек», ИИ молчит: сообщения клиента
 * пересылаются всем telegram-получателям (операторам), а они отвечают через
 * Telegram-«Ответить» на пересланное сообщение. Маппинг «пересланное сообщение →
 * диалог» держим в кэше (Redis), без отдельной таблицы. Команды /close и /bot.
 */
final readonly class TelegramRelayService
{
    private const int MAP_TTL = 60 * 60 * 24 * 7; // 7 дней

    public function __construct(
        private TelegramGateway $telegram,
        private NotificationRecipientRepositoryInterface $recipients,
        private ConversationRepositoryInterface $conversations,
        private MessageRepositoryInterface $messages,
        private ContactCapture $contacts,
        private CacheRepository $cache,
    ) {}

    public function isOperator(string $chatId): bool
    {
        return $this->operatorChatIds()->contains($chatId);
    }

    /**
     * Сообщение от оператора: команда (/close, /bot) или ответ клиенту по reply.
     *
     * @param  array<string, mixed>  $message
     */
    public function handleOperatorMessage(Channel $channel, array $message): void
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        $text = trim((string) ($message['text'] ?? ''));
        $replyId = $message['reply_to_message']['message_id'] ?? null;

        if ($replyId === null) {
            $this->telegram->send($channel, $chatId, 'Чтобы ответить клиенту — используйте «Ответить» на его сообщении.');

            return;
        }

        $conversationId = $this->cache->get($this->key($chatId, (int) $replyId));
        $conversation = $conversationId !== null ? $this->conversations->findForCurrentTenant((string) $conversationId) : null;

        if (! $conversation instanceof Conversation) {
            $this->telegram->send($channel, $chatId, 'Диалог не найден или устарел — дождитесь нового сообщения клиента.');

            return;
        }

        if ($text === '/close') {
            $this->conversations->updateStatus($conversation, ConversationStatus::Closed);
            $this->telegram->send($channel, $chatId, '✅ Диалог закрыт. На новое обращение ответит бот.');

            return;
        }

        if ($text === '/bot') {
            $this->conversations->updateStatus($conversation, ConversationStatus::Open);
            $this->telegram->send($channel, $chatId, '🤖 Готово — бот снова отвечает этому клиенту.');

            return;
        }

        if ($text === '') {
            return;
        }

        // Диалог уже не в режиме оператора (бот возобновлён через /bot или закрыт)
        // — не подмешиваем ответ оператора в бот-диалог (напр. ответ на старое
        // кешированное сообщение спустя время).
        if ($conversation->status !== ConversationStatus::NeedsHuman) {
            $this->telegram->send($channel, $chatId, 'Этот диалог уже не в режиме оператора — бот отвечает сам или диалог закрыт. Ответ клиенту не отправлен.');

            return;
        }

        // Пересылаем ответ оператора клиенту.
        $this->telegram->send($channel, $conversation->external_chat_id, $text);
        $this->messages->recordOutbound($conversation, $text, MessageStatus::Sent);
        $this->conversations->touchLastMessage($conversation);
    }

    /**
     * Если диалог в режиме «нужен человек» — фиксируем входящее и пересылаем
     * операторам (без ИИ). Возвращает true, если сообщение обработано мостом.
     */
    public function relayClientIfNeedsHuman(Channel $channel, IncomingMessage $incoming): bool
    {
        $conversation = $this->conversations->findActiveForChat($channel->id, $incoming->externalChatId);

        if (! $conversation instanceof Conversation || $conversation->status !== ConversationStatus::NeedsHuman) {
            return false;
        }

        $inbound = $this->messages->recordInbound($conversation, $incoming);
        if ($inbound === null) {
            return true; // дубликат вебхука
        }

        $this->contacts->fromInbound($conversation, $incoming->text);
        $this->conversations->touchLastMessage($conversation);
        $this->forwardToOperators($channel, $conversation, '💬 '.$this->label($conversation), $incoming->text);

        return true;
    }

    /**
     * Пересылка операторам сообщения, на котором диалог эскалировался.
     */
    public function forwardEscalation(Channel $channel, IncomingMessage $incoming): void
    {
        $conversation = $this->conversations->findActiveForChat($channel->id, $incoming->externalChatId);

        if ($conversation instanceof Conversation && $conversation->status === ConversationStatus::NeedsHuman) {
            $this->forwardToOperators($channel, $conversation, '🔔 Клиент просит оператора — '.$this->label($conversation), $incoming->text);
        }
    }

    private function forwardToOperators(Channel $channel, Conversation $conversation, string $header, string $clientText): void
    {
        $text = "{$header}\n«{$clientText}»\n\n↩️ Ответьте на это сообщение, чтобы написать клиенту.\n/close — закрыть · /bot — вернуть боту";

        foreach ($this->operatorChatIds() as $chatId) {
            $messageId = $this->telegram->sendReturningId($channel, $chatId, $text);

            if ($messageId !== null) {
                $this->cache->put($this->key($chatId, $messageId), $conversation->id, self::MAP_TTL);
            }
        }
    }

    /**
     * @return Collection<int, string>
     */
    private function operatorChatIds(): Collection
    {
        return $this->recipients->deliverableForCurrentTenant()
            ->filter(fn (NotificationRecipient $r): bool => $r->type === NotificationChannelType::Telegram && $r->value !== null)
            ->map(fn (NotificationRecipient $r): string => (string) $r->value)
            ->values();
    }

    private function label(Conversation $conversation): string
    {
        $display = $conversation->displayName();
        $phoneValue = $conversation->displayPhone();
        $name = $display !== null && $display !== '' ? $display : 'Гость';
        $phone = $phoneValue !== null && $phoneValue !== '' ? " ({$phoneValue})" : '';

        return $name.$phone;
    }

    private function key(string $chatId, int $messageId): string
    {
        return "tgrelay:{$chatId}:{$messageId}";
    }
}
