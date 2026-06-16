<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Ответ бота: текст для отправки, нужно ли эскалировать на администратора,
 * оформлена ли запись и отменил ли клиент запись (запись/отмена закрывают диалог).
 */
final readonly class BotReply
{
    public function __construct(
        public string $text,
        public bool $escalate,
        public bool $booked = false,
        public bool $cancelled = false,
    ) {}
}
