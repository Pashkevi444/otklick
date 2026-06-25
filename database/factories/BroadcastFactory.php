<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Broadcasts\Models\Broadcast;
use App\Shared\Enums\BroadcastRecurrence;
use App\Shared\Enums\BroadcastStatus;
use App\Shared\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Broadcast>
 */
class BroadcastFactory extends Factory
{
    protected $model = Broadcast::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => fake()->sentence(3),
            'body' => fake()->paragraph(),
            'channels' => ['telegram'],
            'status' => BroadcastStatus::Draft,
            'recurrence' => BroadcastRecurrence::None,
            'scheduled_at' => null,
            'next_run_at' => null,
            'last_run_at' => null,
            'sent_count' => 0,
            'failed_count' => 0,
            'created_by' => null,
        ];
    }
}
