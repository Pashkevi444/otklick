<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\CrmSource;

/**
 * Данные лида. Переносятся между сервисом и репозиторием.
 */
final readonly class LeadData
{
    /**
     * @param  array<string, mixed>|null  $custom
     */
    public function __construct(
        public ?string $clientId = null,
        public ?string $conversationId = null,
        public ?string $title = null,
        public CrmSource $source = CrmSource::Manual,
        public ?string $notes = null,
        public ?array $custom = null,
    ) {}
}
