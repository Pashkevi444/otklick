<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Enums\ChannelType;
use App\Models\Tenant;
use App\Services\ChannelService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ChannelServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_connect_telegram_creates_channel_and_clears_webhook_for_polling(): void
    {
        Http::fake();

        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);

        $channel = $this->app->make(ChannelService::class)
            ->connectTelegram($tenant->id, '123456:ABC-token');

        $this->assertSame(ChannelType::Telegram, $channel->type);
        $this->assertSame($tenant->id, $channel->tenant_id);
        $this->assertSame('123456', $channel->external_id);
        $this->assertSame('123456:ABC-token', $channel->botToken());
        $this->assertDatabaseHas('channels', ['id' => $channel->id, 'type' => 'telegram']);

        // Бот работает через long polling: вебхук снимается, а не ставится.
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/deleteWebhook'));
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/setWebhook'));
    }
}
