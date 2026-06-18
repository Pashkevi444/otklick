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
        // Мета-намерение «отменить»/«перенести» запись перебивает всё: работает и
        // во время активного мастера записи, и вне его — чтобы клиент не застревал
        // на текущем шаге (раньше «отмени запись» в середине сценария игнорилось).
        $intercept = $this->booking->interceptIntent($tenant, $conversation, $text);

        if ($intercept !== null) {
            return $intercept;
        }

        $state = $conversation->booking_state;

        if (is_array($state) && $state !== []) {
            return $this->booking->advance($tenant, $conversation, $text);
        }

        $reply = $this->composer->compose($tenant, $conversation, $this->booking->isAvailable());

        if ($reply->startBooking) {
            return $this->booking->start($tenant, $conversation)
                ?? new BotReply($reply->text, escalate: true);
        }

        return $reply;
    }

    /**
     * Клиент отменяет запись ([[CANCELLED]]) — отменяем её и в CRM.
     */
    public function cancelBookingInCrm(Conversation $conversation): void
    {
        $this->booking->cancelLastBooking($conversation);
    }
}
