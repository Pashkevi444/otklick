<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Channels\Models\Channel;
use App\Modules\Clients\Models\Client;
use App\Modules\Conversations\Models\Conversation;
use App\Shared\Enums\ConversationStatus;
use App\Shared\Models\Tenant;
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
            'status' => ConversationStatus::Open,
            'last_message_at' => null,
        ];
    }

    /**
     * Привязывает лид к карточке клиента с заданными именем/телефоном/email
     * (имя/телефон — атрибуты клиента, не лида). Карточка того же тенанта.
     */
    public function withClient(?string $name = null, ?string $phone = null, ?string $email = null): static
    {
        return $this->afterCreating(function (Conversation $conversation) use ($name, $phone, $email): void {
            $client = Client::factory()->create([
                'tenant_id' => $conversation->tenant_id,
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
            ]);
            $conversation->forceFill(['client_id' => $client->id])->save();
            $conversation->setRelation('client', $client);
        });
    }
}
