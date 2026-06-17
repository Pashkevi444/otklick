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

    public function test_connect_vk_creates_channel_and_validates_group(): void
    {
        Http::fake(['*/groups.getById' => Http::response(['response' => ['groups' => [['name' => 'Барбершоп']]]])]);

        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);

        $channel = $this->app->make(ChannelService::class)->connectVk($tenant->id, 'community-token', '42');

        $this->assertSame(ChannelType::Vk, $channel->type);
        $this->assertSame('42', $channel->external_id);
        $this->assertSame('community-token', $channel->credential('access_token'));
        $this->assertSame('42', $channel->credential('group_id'));
        $this->assertDatabaseHas('channels', ['id' => $channel->id, 'type' => 'vk']);
    }

    public function test_connect_vk_rolls_back_when_group_not_confirmed(): void
    {
        // Битый токен/нет прав — groups.getById вернёт ошибку, groupName → null.
        Http::fake(['*/groups.getById' => Http::response(['error' => ['error_code' => 5]])]);

        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);

        try {
            $this->app->make(ChannelService::class)->connectVk($tenant->id, 'bad-token', '42');
            $this->fail('Ожидали исключение при неподтверждённом сообществе.');
        } catch (\RuntimeException) {
            // ожидаемо
        }

        // Канал не остался полу-подключённым (транзакция откатилась).
        $this->assertDatabaseCount('channels', 0);
    }

    public function test_connect_max_creates_channel_and_validates_token(): void
    {
        Http::fake(['*/me' => Http::response(['user_id' => 1, 'name' => 'Бот', 'username' => 'biz_bot'])]);

        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);

        $channel = $this->app->make(ChannelService::class)->connectMax($tenant->id, 'max-token');

        $this->assertSame(ChannelType::Max, $channel->type);
        $this->assertSame('max-token', $channel->credential('access_token'));
        $this->assertDatabaseHas('channels', ['id' => $channel->id, 'type' => 'max']);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/me'));
    }

    public function test_connect_max_rolls_back_on_invalid_token(): void
    {
        // Битый токен — GET /me ответит 401, исключение всплывёт, транзакция откатится.
        Http::fake(['*/me' => Http::response(['code' => 'verify.token'], 401)]);

        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);

        try {
            $this->app->make(ChannelService::class)->connectMax($tenant->id, 'bad-token');
            $this->fail('Ожидали исключение при битом токене MAX.');
        } catch (\Throwable) {
            // ожидаемо (RequestException)
        }

        $this->assertDatabaseCount('channels', 0);
    }
}
