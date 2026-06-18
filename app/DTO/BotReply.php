<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Ответ бота: текст для отправки, нужно ли эскалировать на администратора,
 * оформлена ли запись, отменил ли клиент запись (запись/отмена закрывают диалог)
 * и нужно ли запустить пошаговую запись в CRM (startBooking — обрабатывает
 * BotResponder через BookingFlow).
 *
 * knowledgeGap — эскалация именно из-за того, что бот НЕ нашёл ответа в базе
 * знаний (а не из booking-флоу): такой вопрос фиксируется в «пробелах бота»,
 * чтобы бизнес дополнил базу знаний.
 */
final readonly class BotReply
{
    public function __construct(
        public string $text,
        public bool $escalate,
        public bool $booked = false,
        public bool $cancelled = false,
        public bool $startBooking = false,
        public bool $knowledgeGap = false,
    ) {}
}
