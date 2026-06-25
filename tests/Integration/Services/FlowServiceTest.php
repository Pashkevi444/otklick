<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Modules\Channels\Models\Channel;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Flows\Models\Flow;
use App\Modules\Flows\Models\FlowAbAssignment;
use App\Modules\Flows\Services\FlowService;
use App\Shared\Models\Tenant;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FlowServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ab_stats_compute_conversion_from_bookings(): void
    {
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $flow = Flow::factory()->for($tenant)->create();

        // Вариант A: 3 диалога, 2 с записью; вариант B: 2 диалога, 1 с записью.
        $make = function (string $variant, bool $booked) use ($tenant, $channel, $flow): void {
            $conv = Conversation::factory()->create([
                'tenant_id' => $tenant->id, 'channel_id' => $channel->id,
                'booked_at' => $booked ? now() : null,
            ]);
            FlowAbAssignment::query()->create([
                'tenant_id' => $tenant->id, 'flow_id' => $flow->id, 'conversation_id' => $conv->id, 'variant' => $variant,
            ]);
        };
        foreach ([['A', true], ['A', true], ['A', false], ['B', true], ['B', false]] as [$v, $b]) {
            $make($v, $b);
        }

        $stats = app(TenantInitializer::class)->run($tenant->id, fn (): array => app(FlowService::class)->abStats());

        $byVariant = collect($stats[$flow->id])->keyBy('variant');
        $this->assertSame(3, $byVariant['A']['total']);
        $this->assertSame(2, $byVariant['A']['booked']);
        $this->assertSame(66.7, $byVariant['A']['conversion']);
        $this->assertSame(50.0, $byVariant['B']['conversion']);
    }

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
