<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Ответ бота: текст для отправки, нужно ли эскалировать диалог на администратора
 * и состоялась ли запись (тогда диалог закрывается).
 */
final readonly class BotReply
{
    public function __construct(
        public string $text,
        public bool $escalate,
        public bool $booked = false,
    ) {}
}
