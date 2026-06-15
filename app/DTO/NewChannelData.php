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
        public ?string $secretToken = null,
        /** @var array<string, mixed> */
        public array $settings = [],
    ) {}
}
