<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Профиль бизнеса — «контекст работы», который видит/использует бот, и заодно
 * витрина бизнеса (карточка): описание, сайт, аватар.
 * Хранится в tenants.settings под ключом profile.
 */
final readonly class BusinessProfile
{
    public function __construct(
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $address = null,
        public ?string $workingHours = null,
        public ?string $escalationNote = null,
        public ?string $description = null,
        public ?string $website = null,
        public ?string $avatarPath = null,
        public ?string $avatarUrl = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            phone: $data['phone'] ?? null,
            email: $data['email'] ?? null,
            address: $data['address'] ?? null,
            workingHours: $data['working_hours'] ?? null,
            escalationNote: $data['escalation_note'] ?? null,
            description: $data['description'] ?? null,
            website: $data['website'] ?? null,
            avatarPath: $data['avatar_path'] ?? null,
            avatarUrl: $data['avatar_url'] ?? null,
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'working_hours' => $this->workingHours,
            'escalation_note' => $this->escalationNote,
            'description' => $this->description,
            'website' => $this->website,
            'avatar_path' => $this->avatarPath,
            'avatar_url' => $this->avatarUrl,
        ];
    }
}
