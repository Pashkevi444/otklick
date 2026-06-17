<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Настройки напоминаний клиенту о записи (в рамках интеграции с CRM).
 * Хранятся в CrmConnection.settings['reminders']. offsetsMinutes — за сколько
 * минут до визита напомнить; число элементов = сколько раз напоминать.
 */
final readonly class ReminderSettings
{
    /** Максимум офсетов и горизонт (неделя) — защита от абсурдных значений. */
    private const int MAX_OFFSETS = 5;

    private const int MAX_MINUTES = 7 * 24 * 60;

    /**
     * @param  list<int>  $offsetsMinutes  отсортированы по убыванию, уникальны
     */
    public function __construct(
        public bool $enabled,
        public array $offsetsMinutes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $offsets = array_values(array_filter(array_map(
            static fn ($v): int => (int) $v,
            is_array($data['offsets'] ?? null) ? $data['offsets'] : [],
        ), static fn (int $m): bool => $m > 0 && $m <= self::MAX_MINUTES));

        $offsets = array_values(array_unique($offsets));
        rsort($offsets);
        $offsets = array_slice($offsets, 0, self::MAX_OFFSETS);

        return new self(
            enabled: (bool) ($data['enabled'] ?? false),
            offsetsMinutes: $offsets,
        );
    }

    /**
     * @return array{enabled: bool, offsets: list<int>}
     */
    public function toArray(): array
    {
        return ['enabled' => $this->enabled, 'offsets' => $this->offsetsMinutes];
    }

    public function isActive(): bool
    {
        return $this->enabled && $this->offsetsMinutes !== [];
    }
}
