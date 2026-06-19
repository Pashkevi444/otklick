<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcast;
use App\Models\Broadcast;
use App\Models\BroadcastDelivery;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class BroadcastsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantWithOwner(): array
    {
        $tenant = Tenant::factory()->max()->create(); // право на рассылки

        return [$tenant, User::factory()->owner($tenant)->create()];
    }

    public function test_index_renders_with_audience_count(): void
    {
        [, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)
            ->get('/cabinet/broadcasts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/Broadcasts/Index')
                ->has('audienceCount')
                ->has('channelOptions', 4));
    }

    public function test_store_now_launches_and_queues_delivery(): void
    {
        Queue::fake();
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/broadcasts', [
            'title' => 'Акция',
            'body' => 'Скидка 20%',
            'channels' => ['telegram', 'email'],
            'mode' => 'now',
            'recurrence' => 'none',
        ])->assertRedirect(route('cabinet.broadcasts.index'))->assertSessionHas('success');

        $broadcast = Broadcast::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(BroadcastStatus::Sending, $broadcast->status);
        $this->assertSame(['telegram', 'email'], $broadcast->channels);
        Queue::assertPushed(SendBroadcast::class, 1);
    }

    public function test_store_schedule_sets_scheduled_and_does_not_queue(): void
    {
        Queue::fake();
        [$tenant, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)->post('/cabinet/broadcasts', [
            'title' => 'Напоминание',
            'body' => 'Заходите',
            'channels' => ['telegram'],
            'mode' => 'schedule',
            'scheduled_at' => now()->addDay()->toDateTimeString(),
            'recurrence' => 'weekly',
        ])->assertRedirect(route('cabinet.broadcasts.index'))->assertSessionHas('success');

        $broadcast = Broadcast::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(BroadcastStatus::Scheduled, $broadcast->status);
        $this->assertNotNull($broadcast->next_run_at);
        Queue::assertNotPushed(SendBroadcast::class);
    }

    public function test_validation_requires_channels(): void
    {
        [, $owner] = $this->tenantWithOwner();

        $this->actingAs($owner)
            ->post('/cabinet/broadcasts', ['title' => 'X', 'body' => 'Y', 'channels' => [], 'mode' => 'now'])
            ->assertSessionHasErrors('channels');
    }

    public function test_cancel_removes_from_schedule(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $broadcast = Broadcast::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BroadcastStatus::Scheduled,
            'next_run_at' => now()->addDay(),
        ]);

        $this->actingAs($owner)
            ->post("/cabinet/broadcasts/{$broadcast->id}/cancel")
            ->assertRedirect(route('cabinet.broadcasts.index'));

        $broadcast->refresh();
        $this->assertSame(BroadcastStatus::Canceled, $broadcast->status);
        $this->assertNull($broadcast->next_run_at);
    }

    public function test_destroy_deletes_broadcast(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $broadcast = Broadcast::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)
            ->delete("/cabinet/broadcasts/{$broadcast->id}")
            ->assertRedirect(route('cabinet.broadcasts.index'));

        $this->assertDatabaseMissing('broadcasts', ['id' => $broadcast->id]);
    }

    public function test_show_renders_delivery_log(): void
    {
        [$tenant, $owner] = $this->tenantWithOwner();
        $broadcast = Broadcast::factory()->create(['tenant_id' => $tenant->id, 'status' => BroadcastStatus::Sent]);
        BroadcastDelivery::create([
            'tenant_id' => $tenant->id,
            'broadcast_id' => $broadcast->id,
            'client_id' => null,
            'channel' => 'telegram',
            'target' => '777',
            'status' => 'failed',
            'error' => 'boom',
        ]);

        $this->actingAs($owner)
            ->get("/cabinet/broadcasts/{$broadcast->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/Broadcasts/Show')
                ->has('deliveries', 1)
                ->where('deliveries.0.status', 'failed')
                ->where('deliveries.0.error', 'boom'));
    }

    public function test_plan_without_broadcasts_is_forbidden(): void
    {
        // Стандарт: broadcasts=false — раздел недоступен.
        $tenant = Tenant::factory()->create(['plan' => 'standard']);
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->get('/cabinet/broadcasts')->assertForbidden();
    }
}
