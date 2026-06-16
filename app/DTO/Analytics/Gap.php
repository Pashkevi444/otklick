<?php

declare(strict_types=1);

namespace App\DTO\Analytics;

/**
 * Выявленный пробел — «чего и где не хватает»: что мешает лидам доходить до
 * записи и что с этим сделать.
 */
final readonly class Gap
{
    public const string HIGH = 'high';

    public const string MEDIUM = 'medium';

    public const string LOW = 'low';

    public const string OK = 'ok';

    public function __construct(
        public string $severity,
        public string $title,
        public string $detail,
        public string $action,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'severity' => $this->severity,
            'title' => $this->title,
            'detail' => $this->detail,
            'action' => $this->action,
        ];
    }
}
