<?php

declare(strict_types=1);

namespace Tests\Feature\Vk;

use App\Jobs\ProcessVkUpdate;
use App\Models\Channel;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class PollVkUpdatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_poll_fetches_updates_and_dispatches_jobs(): void
    {
        Bus::fake([ProcessVkUpdate::class]);

        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->vk()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        Http::fake([
            '*/groups.getLongPollServer*' => Http::response([
                'response' => ['server' => 'https://lp.vk.com/wh1', 'key' => 'k1', 'ts' => '50'],
            ]),
            'lp.vk.com/*' => Http::response([
                'ts' => '51',
                'updates' => [['type' => 'message_new', 'object' => ['message' => ['peer_id' => 555, 'text' => 'привет']]]],
            ]),
        ]);

        $this->artisan('vk:poll', ['--once' => true])->assertExitCode(0);

        Bus::assertDispatched(ProcessVkUpdate::class, fn (ProcessVkUpdate $job): bool => $job->channelId === $channel->id
            && ($job->update['type'] ?? null) === 'message_new');

        // ts продвинут и сохранён для следующего прохода.
        $this->assertSame('51', Cache::get("vk:poll:state:{$channel->id}")['ts'] ?? null);
    }

    public function test_failed_two_resets_server_state(): void
    {
        Bus::fake([ProcessVkUpdate::class]);

        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->vk()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        Cache::forever("vk:poll:state:{$channel->id}", ['server' => 'https://lp.vk.com/old', 'key' => 'old', 'ts' => '50']);

        Http::fake(['lp.vk.com/*' => Http::response(['failed' => 2])]);

        $this->artisan('vk:poll', ['--once' => true])->assertExitCode(0);

        // Истёкший ключ — состояние сброшено, следующий проход переинициализирует сервер.
        $this->assertNull(Cache::get("vk:poll:state:{$channel->id}"));
        Bus::assertNotDispatched(ProcessVkUpdate::class);
    }

    public function test_poll_ignores_inactive_channels(): void
    {
        Bus::fake([ProcessVkUpdate::class]);

        $tenant = Tenant::factory()->create();
        Channel::factory()->vk()->create(['tenant_id' => $tenant->id, 'is_active' => false]);

        Http::fake();

        $this->artisan('vk:poll', ['--once' => true])->assertExitCode(0);

        Http::assertNothingSent();
        Bus::assertNotDispatched(ProcessVkUpdate::class);
    }
}
