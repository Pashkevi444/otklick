<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Notifications\Models\NotificationRecipient;
use App\Shared\Enums\NotificationChannelType;
use App\Shared\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationRecipient>
 */
class NotificationRecipientFactory extends Factory
{
    protected $model = NotificationRecipient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'type' => NotificationChannelType::Email,
            'value' => fake()->safeEmail(),
            'label' => null,
            'is_active' => true,
            'link_token' => null,
            'verified_at' => now(),
            'role' => 'director',
            'events' => [],
        ];
    }

    public function telegram(string $chatId = '123456'): static
    {
        return $this->state(fn (): array => [
            'type' => NotificationChannelType::Telegram,
            'value' => $chatId,
        ]);
    }
}
