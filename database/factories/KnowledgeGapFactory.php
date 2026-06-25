<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Knowledge\Models\KnowledgeGap;
use App\Shared\Enums\KnowledgeGapStatus;
use App\Shared\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeGap>
 */
class KnowledgeGapFactory extends Factory
{
    protected $model = KnowledgeGap::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $question = fake()->sentence();

        return [
            'tenant_id' => Tenant::factory(),
            'question' => $question,
            'normalized' => mb_substr(trim(mb_strtolower($question)), 0, 255),
            'occurrences' => 1,
            'conversation_id' => null,
            'channel_type' => 'telegram',
            'status' => KnowledgeGapStatus::Open,
            'last_seen_at' => now(),
        ];
    }
}
