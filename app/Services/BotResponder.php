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
        private readonly ContactGate $contacts,
        private readonly FlowEngine $flows,
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

        // Контактная форма в начале диалога: новому клиенту — приветствие + запрос
        // имени/телефона/email с валидацией; узнанному — здороваемся по имени.
        $gate = $this->contacts->handle($tenant, $conversation, $text);

        if ($gate !== null) {
            return $gate;
        }

        $state = $conversation->booking_state;

        if (is_array($state) && $state !== []) {
            return $this->booking->advance($tenant, $conversation, $text);
        }

        // Сценарии-воронки (no-code логика владельца): продолжаем активную воронку
        // или запускаем по триггеру — ДО LLM. Не сработали → отвечает ИИ по базе
        // знаний (свободный текст не по кнопкам выводит из воронки).
        $flow = $this->flows->handle($tenant, $conversation, $text);

        if ($flow !== null) {
            return $flow;
        }

        // Явный выбор «Новая запись» из меню записей — заводим свежую запись,
        // минуя меню и LLM (иначе [[BOOK]] снова показал бы меню — зацикливание).
        if (mb_strtolower(trim($text)) === 'новая запись') {
            return $this->booking->start($tenant, $conversation)
                ?? new BotReply($text, escalate: true);
        }

        // Запись предлагаем только при праве на CRM-интеграцию (YClients) И активном
        // подключении. Нет права — бот не зовёт к записи (она уходит на человека).
        $bookingEnabled = $tenant->features()->crm && $this->booking->isAvailable();
        $reply = $this->composer->compose($tenant, $conversation, $bookingEnabled);

        if ($reply->startBooking) {
            // У клиента уже есть предстоящая запись → предлагаем выбор (перенести/
            // отменить/новая), а не молча заводим вторую.
            $menu = $this->booking->bookingChoiceMenu($conversation);
            if ($menu !== null) {
                return $menu;
            }

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
