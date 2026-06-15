<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConversationStatus;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            // Канал того же тенанта — когерентно для RLS.
            'channel_id' => fn (array $attributes): string => Channel::factory()
                ->create(['tenant_id' => $attributes['tenant_id']])->id,
            'external_chat_id' => (string) fake()->unique()->numberBetween(1, 999999999),
            'contact_name' => fake()->name(),
            'status' => ConversationStatus::Open,
            'last_message_at' => null,
        ];
    }
}
