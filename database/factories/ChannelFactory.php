<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Channel>
 */
class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'type' => ChannelType::Telegram,
            'external_id' => (string) fake()->unique()->numberBetween(100000, 999999999),
            'credentials' => [
                'bot_token' => fake()->unique()->numerify('#########').':'.Str::random(35),
            ],
            'is_active' => true,
            'settings' => [],
        ];
    }

    /**
     * Канал ВКонтакте: токен сообщества + group_id вместо bot_token.
     */
    public function vk(): static
    {
        return $this->state(function (): array {
            $groupId = (string) fake()->unique()->numberBetween(100000, 999999999);

            return [
                'type' => ChannelType::Vk,
                'external_id' => $groupId,
                'credentials' => [
                    'access_token' => 'vk1.a.'.Str::random(80),
                    'group_id' => $groupId,
                ],
            ];
        });
    }

    /**
     * Канал MAX: один токен бота (без group_id).
     */
    public function max(): static
    {
        return $this->state(fn (): array => [
            'type' => ChannelType::Max,
            'external_id' => null,
            'credentials' => ['access_token' => 'max-'.Str::random(50)],
        ]);
    }
}
