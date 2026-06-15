<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Данные записи базы знаний. Переносятся между сервисом и репозиторием.
 */
final readonly class KnowledgeEntryData
{
    public function __construct(
        public string $title,
        public string $content,
        public bool $isPublished,
    ) {}
}
