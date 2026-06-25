<?php

declare(strict_types=1);

namespace App\Modules\Notifications\DTO;

/**
 * Готовое к доставке уведомление владельцу: тема и текст. Канал-агностично —
 * каждый нотификатор (email/telegram) отдаёт это по-своему.
 */
final readonly class OwnerNotification
{
    public function __construct(
        public string $subject,
        public string $body,
    ) {}
}
