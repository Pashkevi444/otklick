<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Нормализованное входящее сообщение от клиента, не зависящее от конкретного
 * канала. Парсится из сырого апдейта канала (например, Telegram) и передаётся
 * в бизнес-логику.
 */
final readonly class IncomingMessage
{
    public function __construct(
        public string $externalChatId,
        public string $externalMessageId,
        public string $text,
        public ?string $contactName = null,
        /** Ссылка на аккаунт клиента в мессенджере (для деталей диалога). */
        public ?string $contactRef = null,
        /** @var array<string, mixed> */
        public array $raw = [],
    ) {}
}
