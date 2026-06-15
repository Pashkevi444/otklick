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

    public function test_connect_telegram_creates_channel_and_registers_webhook(): void
    {
        Http::fake();

        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);

        $channel = $this->app->make(ChannelService::class)
            ->connectTelegram($tenant->id, '123456:ABC-token', 'https://app.example');

        $this->assertSame(ChannelType::Telegram, $channel->type);
        $this->assertSame($tenant->id, $channel->tenant_id);
        $this->assertSame('123456', $channel->external_id);
        $this->assertSame('123456:ABC-token', $channel->botToken());
        $this->assertDatabaseHas('channels', ['id' => $channel->id, 'type' => 'telegram']);

        // Вебхук зарегистрирован у Telegram на адрес с tenant/channel и secret_token.
        Http::assertSent(function ($request) use ($tenant, $channel): bool {
            return str_contains($request->url(), '/setWebhook')
                && $request['url'] === "https://app.example/webhooks/telegram/{$tenant->id}/{$channel->id}"
                && $request['secret_token'] === $channel->secretToken();
        });
    }
}
