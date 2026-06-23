<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\GridEntity;

/**
 * Данные сохранённого вида грида.
 */
final readonly class GridViewData
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public GridEntity $entity,
        public string $name,
        public array $config,
        public int $userId,
    ) {}
}
