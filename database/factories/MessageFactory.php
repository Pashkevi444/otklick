<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Shared\Enums\MessageDirection;
use App\Shared\Enums\MessageStatus;
use App\Shared\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            // Диалог того же тенанта — когерентно для RLS.
            'conversation_id' => fn (array $attributes): string => Conversation::factory()
                ->create(['tenant_id' => $attributes['tenant_id']])->id,
            'direction' => MessageDirection::Inbound,
            'external_message_id' => (string) fake()->unique()->numberBetween(1, 999999999),
            'text' => fake()->sentence(),
            'payload' => null,
            'status' => MessageStatus::Received,
        ];
    }
}
