<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Modules\Knowledge\Repositories\Contracts\KnowledgeGapRepositoryInterface;
use App\Shared\Enums\KnowledgeGapStatus;
use App\Shared\Models\Tenant;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class KnowledgeGapRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repo(): KnowledgeGapRepositoryInterface
    {
        return $this->app->make(KnowledgeGapRepositoryInterface::class);
    }

    public function test_record_dedupes_same_question_and_counts_occurrences(): void
    {
        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);

        $this->repo()->record('Есть ли парковка?', 'conv-1', 'telegram');
        $this->repo()->record('есть ли парковка?  ', 'conv-2', 'vk'); // тот же вопрос (нормализация)
        $this->repo()->record('А во сколько открываетесь?', null, 'telegram');

        $open = $this->repo()->openForCurrentTenant();

        $this->assertCount(2, $open); // два разных вопроса
        $this->assertSame(2, $this->repo()->countOpenForCurrentTenant());

        $parking = $open->firstWhere('normalized', 'есть ли парковка?');
        $this->assertSame(2, $parking->occurrences);
        $this->assertSame('conv-2', $parking->conversation_id); // обновлён на последний
    }

    public function test_resolved_gap_is_not_deduped_into(): void
    {
        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);

        $gap = $this->repo()->record('Есть ли доставка?', null, 'telegram');
        $this->repo()->updateStatus($gap, KnowledgeGapStatus::Resolved);

        // Тот же вопрос после закрытия — заводится новый открытый пробел.
        $this->repo()->record('Есть ли доставка?', null, 'telegram');

        $this->assertSame(1, $this->repo()->countOpenForCurrentTenant());
    }
}
