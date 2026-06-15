<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Возможности тарифа (что доступно в кабинете на данном уровне подписки).
 * Источник истины для гейтинга — здесь и в App\Enums\TenantPlan::features().
 */
final readonly class PlanFeatures
{
    public function __construct(
        public int $maxOperators,
        public bool $crm,
        public bool $analytics,
        public bool $broadcasts,
        public bool $clientBase,
        public bool $allChannels,
        public bool $webWidget,
    ) {}

    /**
     * Доступна ли булева возможность по её ключу (для middleware/гейтов).
     */
    public function has(string $feature): bool
    {
        return match ($feature) {
            'crm' => $this->crm,
            'analytics' => $this->analytics,
            'broadcasts' => $this->broadcasts,
            'clientBase' => $this->clientBase,
            'allChannels' => $this->allChannels,
            'webWidget' => $this->webWidget,
            default => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'maxOperators' => $this->maxOperators,
            'crm' => $this->crm,
            'analytics' => $this->analytics,
            'broadcasts' => $this->broadcasts,
            'clientBase' => $this->clientBase,
            'allChannels' => $this->allChannels,
            'webWidget' => $this->webWidget,
        ];
    }
}
