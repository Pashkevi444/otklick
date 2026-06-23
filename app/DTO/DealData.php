<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\CrmSource;

/**
 * Данные сделки. Переносятся между сервисом и репозиторием.
 */
final readonly class DealData
{
    /**
     * @param  array<string, mixed>|null  $custom
     */
    public function __construct(
        public string $stageId,
        public ?string $clientId = null,
        public ?string $title = null,
        public ?int $value = null,
        public ?int $assignedUserId = null,
        public CrmSource $source = CrmSource::Manual,
        public ?string $notes = null,
        public ?array $custom = null,
    ) {}
}
