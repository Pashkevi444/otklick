<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Knowledge\Models\KnowledgeEntry;
use App\Shared\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeEntry>
 */
class KnowledgeEntryFactory extends Factory
{
    protected $model = KnowledgeEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => fake()->sentence(3),
            'content' => fake()->paragraph(),
            'is_published' => true,
            'links' => [],
            'images' => [],
        ];
    }
}
