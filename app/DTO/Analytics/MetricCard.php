<?php

declare(strict_types=1);

namespace App\DTO\Analytics;

/**
 * KPI-карточка дашборда: число + динамика к прошлому периоду.
 */
final readonly class MetricCard
{
    public function __construct(
        public string $key,
        public string $label,
        public int|float $value,
        public string $unit,
        public ?float $deltaPct,
        /** true — рост это хорошо (лиды, конверсия); false — рост это плохо (эскалации). */
        public bool $goodWhenUp,
        public string $hint,
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
            'unit' => $this->unit,
            'deltaPct' => $this->deltaPct,
            'goodWhenUp' => $this->goodWhenUp,
            'hint' => $this->hint,
        ];
    }
}
