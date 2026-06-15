<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Jobs\ProcessTelegramUpdate;
use App\Models\Channel;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: Channel}
     */
    private function tenantWithChannel(): array
    {
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);

        return [$tenant, $channel];
    }

    /**
     * @return array<string, mixed>
     */
    private function update(): array
    {
        return [
            'update_id' => 100,
            'message' => [
                'message_id' => 10,
                'chat' => ['id' => 555],
                'text' => 'привет',
                'from' => ['first_name' => 'Иван'],
            ],
        ];
    }

    public function test_valid_webhook_acks_200_and_queues_processing(): void
    {
        Queue::fake();
        [$tenant, $channel] = $this->tenantWithChannel();

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', (string) $channel->secretToken())
            ->postJson("/webhooks/telegram/{$tenant->id}/{$channel->id}", $this->update());

        $response->assertOk();

        Queue::assertPushed(
            ProcessTelegramUpdate::class,
            fn (ProcessTelegramUpdate $job): bool => $job->tenantId === $tenant->id
                && $job->channelId === $channel->id
                && $job->update['update_id'] === 100,
        );
    }

    public function test_wrong_secret_is_forbidden_and_nothing_queued(): void
    {
        Queue::fake();
        [$tenant, $channel] = $this->tenantWithChannel();

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'wrong-secret')
            ->postJson("/webhooks/telegram/{$tenant->id}/{$channel->id}", $this->update());

        $response->assertForbidden();
        Queue::assertNothingPushed();
    }

    public function test_unknown_channel_returns_404(): void
    {
        Queue::fake();
        $tenant = Tenant::factory()->create();

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'whatever')
            ->postJson("/webhooks/telegram/{$tenant->id}/00000000-0000-0000-0000-000000000000", $this->update());

        $response->assertNotFound();
        Queue::assertNothingPushed();
    }

    public function test_channel_of_another_tenant_is_not_reachable(): void
    {
        Queue::fake();
        $tenantA = Tenant::factory()->create();
        [$tenantB, $channelB] = $this->tenantWithChannel();

        // Канал тенанта B недоступен под tenant_id тенанта A в URL.
        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', (string) $channelB->secretToken())
            ->postJson("/webhooks/telegram/{$tenantA->id}/{$channelB->id}", $this->update());

        $response->assertNotFound();
        Queue::assertNothingPushed();
    }
}
