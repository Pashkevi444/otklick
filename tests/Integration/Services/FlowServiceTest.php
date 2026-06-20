<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Models\Tenant;
use App\Services\FlowService;
use App\Tenancy\TenantInitializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FlowServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_computes_one_embedding_per_trigger(): void
    {
        $tenant = Tenant::factory()->create();

        $flow = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowService::class)->create($tenant->id, [
            'name' => 'Акции',
            'is_active' => true,
            'triggers' => ['акция', 'скидка'],
            'definition' => ['start' => 'n1', 'nodes' => ['n1' => ['type' => 'message', 'text' => 'Привет', 'action' => 'end', 'options' => []]]],
        ]));

        $this->assertIsArray($flow->trigger_embeddings);
        $this->assertCount(2, $flow->trigger_embeddings); // по вектору на триггер
        $this->assertIsArray($flow->trigger_embeddings[0]);
    }

    public function test_create_without_triggers_stores_null(): void
    {
        $tenant = Tenant::factory()->create();

        $flow = app(TenantInitializer::class)->run($tenant->id, fn () => app(FlowService::class)->create($tenant->id, [
            'name' => 'Без триггеров',
            'is_active' => false,
            'triggers' => [],
            'definition' => ['start' => 'n1', 'nodes' => []],
        ]));

        $this->assertNull($flow->trigger_embeddings);
    }

    public function test_update_recomputes_embeddings_when_triggers_change(): void
    {
        $tenant = Tenant::factory()->create();

        app(TenantInitializer::class)->run($tenant->id, function () use ($tenant): void {
            $service = app(FlowService::class);
            $flow = $service->create($tenant->id, [
                'name' => 'Акции', 'is_active' => true, 'triggers' => ['акция'],
                'definition' => ['start' => 'n1', 'nodes' => ['n1' => ['type' => 'message', 'text' => 'x', 'action' => 'end', 'options' => []]]],
            ]);

            $service->update($flow, ['triggers' => ['акция', 'распродажа', 'промо']]);

            $this->assertCount(3, $flow->fresh()->trigger_embeddings);
        });
    }
}
