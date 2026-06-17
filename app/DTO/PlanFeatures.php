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
        public int $maxNotifyEmail = 1,
        public int $maxNotifyTelegram = 4,
        public bool $reminders = false,
    ) {}

    /**
     * Накладывает индивидуальные оверрайды бизнеса (из settings['overrides'])
     * поверх базовых возможностей тарифа. Присутствующие ключи переопределяют.
     *
     * @param  array<string, mixed>  $o
     */
    public function merge(array $o): self
    {
        return new self(
            maxOperators: (int) ($o['maxOperators'] ?? $this->maxOperators),
            crm: (bool) ($o['crm'] ?? $this->crm),
            analytics: (bool) ($o['analytics'] ?? $this->analytics),
            broadcasts: (bool) ($o['broadcasts'] ?? $this->broadcasts),
            clientBase: (bool) ($o['clientBase'] ?? $this->clientBase),
            allChannels: (bool) ($o['allChannels'] ?? $this->allChannels),
            webWidget: (bool) ($o['webWidget'] ?? $this->webWidget),
            maxNotifyEmail: (int) ($o['maxNotifyEmail'] ?? $this->maxNotifyEmail),
            maxNotifyTelegram: (int) ($o['maxNotifyTelegram'] ?? $this->maxNotifyTelegram),
            reminders: (bool) ($o['reminders'] ?? $this->reminders),
        );
    }

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
            'reminders' => $this->reminders,
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
            'maxNotifyEmail' => $this->maxNotifyEmail,
            'maxNotifyTelegram' => $this->maxNotifyTelegram,
            'reminders' => $this->reminders,
        ];
    }
}
