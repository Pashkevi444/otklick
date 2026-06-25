<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\ChannelType;

/**
 * Данные для подключения нового канала тенанта. Переносятся между сервисом и
 * репозиторием.
 */
final readonly class NewChannelData
{
    public function __construct(
        public string $tenantId,
        public ChannelType $type,
        public ?string $externalId,
        public ?string $botToken = null,
        /**
         * Произвольные креды канала (provider-специфика: например VK
         * access_token/group_id). Сливаются с bot_token.
         *
         * @var array<string, string|null>
         */
        public array $credentials = [],
        /** @var array<string, mixed> */
        public array $settings = [],
    ) {}
}
