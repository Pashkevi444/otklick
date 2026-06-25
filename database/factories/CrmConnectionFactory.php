<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Booking\Models\CrmConnection;
use App\Shared\Enums\CrmProvider;
use App\Shared\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CrmConnection>
 */
class CrmConnectionFactory extends Factory
{
    protected $model = CrmConnection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'provider' => CrmProvider::Yclients,
            'credentials' => [
                'company_id' => (string) fake()->unique()->numberBetween(100000, 999999),
                'api_token' => Str::random(40),
            ],
            'is_active' => true,
            'settings' => [],
        ];
    }
}
