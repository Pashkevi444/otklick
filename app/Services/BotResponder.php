<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\BotReply;
use App\Models\Conversation;
use App\Models\Tenant;

/**
 * Выбирает, кто отвечает клиенту: обычный бот по базе знаний (ReplyComposer)
 * или пошаговый мастер записи в CRM (BookingFlow).
 *
 * Если у диалога активна запись (booking_state) — ведёт её мастер. Иначе
 * отвечает бот; и если бот сигналит о намерении записаться ([[BOOK]] →
 * startBooking) и автозапись доступна — запускаем мастер записи.
 *
 * Не final/readonly намеренно — мокается в юнит-тестах сервисов-вызывателей.
 */
class BotResponder
{
    public function __construct(
        private readonly ReplyComposer $composer,
        private readonly BookingFlow $booking,
    ) {}

    public function respond(Tenant $tenant, Conversation $conversation, string $text): BotReply
    {
        $state = $conversation->booking_state;

        if (is_array($state) && $state !== []) {
            return $this->booking->advance($conversation, $text);
        }

        $reply = $this->composer->compose($tenant, $conversation, $this->booking->isAvailable());

        if ($reply->startBooking) {
            return $this->booking->start($conversation)
                ?? new BotReply($reply->text, escalate: true);
        }

        return $reply;
    }
}
