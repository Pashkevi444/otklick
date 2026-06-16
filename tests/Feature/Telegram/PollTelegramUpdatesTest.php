<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Enums\ChannelType;
use App\Jobs\ProcessTelegramUpdate;
use App\Models\Channel;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class PollTelegramUpdatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_poll_fetches_updates_and_dispatches_jobs(): void
    {
        Bus::fake([ProcessTelegramUpdate::class]);

        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => ChannelType::Telegram,
            'is_active' => true,
        ]);

        Http::fake([
            '*/deleteWebhook*' => Http::response(['ok' => true, 'result' => true]),
            '*/getUpdates*' => Http::response(['ok' => true, 'result' => [
                ['update_id' => 100, 'message' => ['message_id' => 1, 'chat' => ['id' => 55], 'text' => 'привет', 'from' => ['first_name' => 'Иван']]],
            ]]),
        ]);

        $this->artisan('telegram:poll', ['--once' => true])->assertExitCode(0);

        // Вебхук снят, апдейт ушёл в очередь, offset продвинут (update_id + 1).
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/deleteWebhook'));
        Bus::assertDispatched(ProcessTelegramUpdate::class, fn (ProcessTelegramUpdate $job): bool => $job->channelId === $channel->id
            && ($job->update['update_id'] ?? null) === 100);
        $this->assertSame(101, (int) Cache::get("telegram:poll:offset:{$channel->id}"));
    }

    public function test_poll_ignores_inactive_channels(): void
    {
        Bus::fake([ProcessTelegramUpdate::class]);

        $tenant = Tenant::factory()->create();
        Channel::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => ChannelType::Telegram,
            'is_active' => false,
        ]);

        Http::fake();

        $this->artisan('telegram:poll', ['--once' => true])->assertExitCode(0);

        Http::assertNothingSent();
        Bus::assertNotDispatched(ProcessTelegramUpdate::class);
    }
}
