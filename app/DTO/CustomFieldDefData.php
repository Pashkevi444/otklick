<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;

/**
 * Данные определения кастомного поля. `key` генерируется сервисом из подписи.
 */
final readonly class CustomFieldDefData
{
    /**
     * @param  array<int, string>|null  $options
     */
    public function __construct(
        public CustomFieldEntity $entity,
        public string $label,
        public CustomFieldType $type,
        public ?array $options = null,
    ) {}
}
