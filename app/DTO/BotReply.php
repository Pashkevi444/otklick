<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Ответ бота: текст для отправки и нужно ли эскалировать диалог на администратора.
 */
final readonly class BotReply
{
    public function __construct(
        public string $text,
        public bool $escalate,
    ) {}
}
