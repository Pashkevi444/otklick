<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Clients\Models\Client;
use App\Shared\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'phone' => '+7'.fake()->unique()->numerify('##########'),
            'email' => null,
            'telegram_username' => null,
            'first_channel_type' => 'telegram',
            'first_seen_at' => now()->subDays(3),
            'last_seen_at' => now(),
            'summary' => null,
            'summary_generated_at' => null,
            'notes' => null,
        ];
    }
}
