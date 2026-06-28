<?php

declare(strict_types=1);

namespace Tests\Feature\Max;

use App\Modules\Channels\Jobs\ProcessMaxUpdate;
use App\Modules\Channels\Models\Channel;
use App\Shared\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class PollMaxUpdatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_poll_fetches_updates_and_dispatches_jobs(): void
    {
        Bus::fake([ProcessMaxUpdate::class]);

        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->max()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        Http::fake(['*/updates*' => Http::response([
            'updates' => [['update_type' => 'message_created', 'message' => ['recipient' => ['chat_id' => 555], 'body' => ['text' => 'привет', 'mid' => 'm1']]]],
            'marker' => 77,
        ])]);

        $this->artisan('max:poll', ['--once' => true])->assertExitCode(0);

        Bus::assertDispatched(ProcessMaxUpdate::class, fn (ProcessMaxUpdate $job): bool => $job->channelId === $channel->id
            && ($job->update['update_type'] ?? null) === 'message_created');

        // marker продвинут и сохранён для следующего прохода.
        $this->assertSame(77, (int) Cache::get("max:poll:marker:{$channel->id}"));
    }

    public function test_poll_fetches_all_active_channels_in_one_round(): void
    {
        // Конкурентный опрос: оба активных канала тянутся за один круг (раньше — по
        // очереди), джоба ставится на каждый, marker продвигается у каждого.
        Bus::fake([ProcessMaxUpdate::class]);

        $tenant = Tenant::factory()->create();
        $ch1 = Channel::factory()->max()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $ch2 = Channel::factory()->max()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        Http::fake(['*/updates*' => Http::response([
            'updates' => [['update_type' => 'message_created', 'message' => ['recipient' => ['chat_id' => 555], 'body' => ['text' => 'привет', 'mid' => 'm1']]]],
            'marker' => 88,
        ])]);

        $this->artisan('max:poll', ['--once' => true])->assertExitCode(0);

        Bus::assertDispatched(ProcessMaxUpdate::class, fn (ProcessMaxUpdate $job): bool => $job->channelId === $ch1->id);
        Bus::assertDispatched(ProcessMaxUpdate::class, fn (ProcessMaxUpdate $job): bool => $job->channelId === $ch2->id);
        $this->assertSame(88, (int) Cache::get("max:poll:marker:{$ch1->id}"));
        $this->assertSame(88, (int) Cache::get("max:poll:marker:{$ch2->id}"));
    }

    public function test_poll_ignores_inactive_channels(): void
    {
        Bus::fake([ProcessMaxUpdate::class]);

        $tenant = Tenant::factory()->create();
        Channel::factory()->max()->create(['tenant_id' => $tenant->id, 'is_active' => false]);

        Http::fake();

        $this->artisan('max:poll', ['--once' => true])->assertExitCode(0);

        Http::assertNothingSent();
        Bus::assertNotDispatched(ProcessMaxUpdate::class);
    }
}
