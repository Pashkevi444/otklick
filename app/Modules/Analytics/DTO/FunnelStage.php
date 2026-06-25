<?php

declare(strict_types=1);

namespace App\Modules\Analytics\DTO;

/**
 * Этап воронки лида: обращение → диалог → контакт → запись.
 */
final readonly class FunnelStage
{
    public function __construct(
        public string $key,
        public string $label,
        public int $value,
        /** Доля от первого этапа воронки (0..100). */
        public float $pct,
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
        ];
    }
}
