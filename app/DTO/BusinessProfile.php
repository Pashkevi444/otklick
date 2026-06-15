<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Профиль бизнеса — «контекст работы», который видит/использует бот.
 * Хранится в tenants.settings под ключом profile.
 */
final readonly class BusinessProfile
{
    public function __construct(
        public ?string $phone = null,
        public ?string $address = null,
        public ?string $workingHours = null,
        public ?string $escalationNote = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            phone: $data['phone'] ?? null,
            address: $data['address'] ?? null,
            workingHours: $data['working_hours'] ?? null,
            escalationNote: $data['escalation_note'] ?? null,
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'phone' => $this->phone,
            'address' => $this->address,
            'working_hours' => $this->workingHours,
            'escalation_note' => $this->escalationNote,
        ];
    }
}
