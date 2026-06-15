<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TenantPlan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'plan' => TenantPlan::Trial,
            'settings' => [],
        ];
    }

    public function standard(): static
    {
        return $this->state(fn (): array => ['plan' => TenantPlan::Standard]);
    }

    public function max(): static
    {
        return $this->state(fn (): array => ['plan' => TenantPlan::Max]);
    }
}
