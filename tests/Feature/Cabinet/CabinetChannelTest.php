<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Models\Channel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class CabinetChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_only_current_tenant_channels(): void
    {
        $tenantA = Tenant::factory()->create();
        $ownerA = User::factory()->owner($tenantA)->create();
        Channel::factory()->create(['tenant_id' => $tenantA->id]);

        $tenantB = Tenant::factory()->create();
        Channel::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAs($ownerA)
            ->get('/cabinet/channels')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/Channels/Index')
                ->has('channels', 1));
    }

    public function test_owner_connects_telegram_bot(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)
            ->post('/cabinet/channels', ['bot_token' => '123456:ABCdef_token'])
            ->assertRedirect(route('cabinet.channels.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('channels', [
            'tenant_id' => $tenant->id,
            'type' => 'telegram',
            'external_id' => '123456',
        ]);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/setWebhook'));
    }

    public function test_invalid_token_is_rejected(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)
            ->post('/cabinet/channels', ['bot_token' => 'not-a-token'])
            ->assertSessionHasErrors('bot_token');

        $this->assertDatabaseCount('channels', 0);
        Http::assertNothingSent();
    }

    public function test_telegram_timeout_shows_friendly_error_not_500(): void
    {
        // api.telegram.org недоступен (таймаут) — не должно быть 500.
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Connection timed out'));
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)
            ->from('/cabinet/channels')
            ->post('/cabinet/channels', ['bot_token' => '123456:ABCdef_token'])
            ->assertRedirect('/cabinet/channels')
            ->assertSessionHasErrors('bot_token');

        // Канал не должен остаться полу-подключённым (транзакция откатилась).
        $this->assertDatabaseCount('channels', 0);
    }

    public function test_owner_disconnects_own_channel(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)
            ->delete("/cabinet/channels/{$channel->id}")
            ->assertRedirect(route('cabinet.channels.index'));

        $this->assertDatabaseMissing('channels', ['id' => $channel->id]);
    }

    public function test_owner_cannot_disconnect_another_tenants_channel(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $otherTenant = Tenant::factory()->create();
        $otherChannel = Channel::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->actingAs($owner)
            ->delete("/cabinet/channels/{$otherChannel->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('channels', ['id' => $otherChannel->id]);
    }

    public function test_super_admin_cannot_access_cabinet(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/cabinet/channels')->assertForbidden();
    }
}
