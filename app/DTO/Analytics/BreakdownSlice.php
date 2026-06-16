<?php

declare(strict_types=1);

namespace App\DTO\Analytics;

/**
 * Доля в разбивке (по каналу / статусу) — для круговых диаграмм и легенд.
 */
final readonly class BreakdownSlice
{
    public function __construct(
        public string $key,
        public string $label,
        public int $value,
        public float $pct,
        public string $color,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'value' => $this->value,
            'pct' => $this->pct,
            'color' => $this->color,
        ];
    }
}
