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
                'secret_token' => Str::random(40),
            ],
            'is_active' => true,
            'settings' => [],
        ];
    }
}
