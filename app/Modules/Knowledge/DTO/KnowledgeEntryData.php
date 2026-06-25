<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\DTO;

/**
 * Данные записи базы знаний. Переносятся между сервисом и репозиторием.
 */
final readonly class KnowledgeEntryData
{
    /**
     * @param  list<array{label: string, url: string}>  $links
     * @param  list<array{path: string, url: string}>  $images
     */
    public function __construct(
        public string $title,
        public string $content,
        public bool $isPublished,
        public array $links = [],
        public array $images = [],
    ) {}
}
