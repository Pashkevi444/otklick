<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\BroadcastStatus;
use App\Jobs\SendBroadcast;
use App\Models\Broadcast;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class RunDueBroadcastsTest extends TestCase
{
    use RefreshDatabase;

    public function test_launches_due_scheduled_broadcast(): void
    {
        Queue::fake();
        $tenant = Tenant::factory()->max()->create();
        $broadcast = Broadcast::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BroadcastStatus::Scheduled,
            'next_run_at' => now()->subMinute(),
            'channels' => ['telegram'],
        ]);

        Artisan::call('broadcasts:run-due');

        Queue::assertPushed(SendBroadcast::class, 1);
        $this->assertSame(BroadcastStatus::Sending, $broadcast->fresh()->status);
    }

    public function test_does_not_launch_future_broadcast(): void
    {
        Queue::fake();
        $tenant = Tenant::factory()->max()->create();
        Broadcast::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BroadcastStatus::Scheduled,
            'next_run_at' => now()->addDay(),
        ]);

        Artisan::call('broadcasts:run-due');

        Queue::assertNotPushed(SendBroadcast::class);
    }

    public function test_ignores_tenant_without_broadcasts_feature(): void
    {
        Queue::fake();
        $tenant = Tenant::factory()->create(['plan' => 'standard']); // broadcasts=false
        Broadcast::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => BroadcastStatus::Scheduled,
            'next_run_at' => now()->subMinute(),
        ]);

        Artisan::call('broadcasts:run-due');

        Queue::assertNotPushed(SendBroadcast::class);
    }
}
