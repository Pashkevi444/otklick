<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Modules\Broadcasts\Mail\BroadcastMail;
use App\Modules\Broadcasts\Models\Broadcast;
use App\Modules\Broadcasts\Repositories\Contracts\BroadcastRepositoryInterface;
use App\Modules\Broadcasts\Services\BroadcastService;
use App\Modules\Channels\ChannelGatewayResolver;
use App\Modules\Channels\ChannelsApiService;
use App\Modules\Channels\Models\Channel;
use App\Modules\Channels\Repositories\Contracts\ChannelRepositoryInterface;
use App\Modules\Channels\Telegram\TelegramGateway;
use App\Modules\Clients\Contracts\ClientsApi;
use App\Modules\Clients\Models\Client;
use App\Modules\Conversations\Models\Conversation;
use App\Shared\Enums\BroadcastRecurrence;
use App\Shared\Enums\BroadcastStatus;
use App\Shared\Enums\ChannelType;
use App\Shared\Models\Tenant;
use App\Shared\Tenancy\TenantInitializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\RecordingChannelGateway;
use Tests\TestCase;

final class BroadcastServiceTest extends TestCase
{
    use RefreshDatabase;

    private RecordingChannelGateway $telegram;

    private function service(bool $throws = false): BroadcastService
    {
        $this->telegram = new RecordingChannelGateway(ChannelType::Telegram, $throws);

        return new BroadcastService(
            app(BroadcastRepositoryInterface::class),
            app(ClientsApi::class),
            new ChannelsApiService(new ChannelGatewayResolver([$this->telegram]), app(ChannelRepositoryInterface::class), app(TelegramGateway::class)),
        );
    }

    private function deliver(Broadcast $broadcast, bool $throws = false): Broadcast
    {
        $service = $this->service($throws);

        app(TenantInitializer::class)->run($broadcast->tenant_id, fn () => $service->deliver($broadcast));

        return Broadcast::withoutGlobalScopes()->findOrFail($broadcast->id);
    }

    private function clientWithTelegram(Tenant $tenant, string $chatId, ?string $email = null): Client
    {
        $channel = Channel::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => ChannelType::Telegram->value,
            'is_active' => true,
        ]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'email' => $email, 'marketing_opt_out' => false]);
        Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'client_id' => $client->id,
            'external_chat_id' => $chatId,
        ]);

        return $client;
    }

    public function test_delivers_to_messenger_and_email_and_counts(): void
    {
        Mail::fake();
        $tenant = Tenant::factory()->max()->create();
        $this->clientWithTelegram($tenant, '777', 'client@example.com');

        $broadcast = Broadcast::factory()->create([
            'tenant_id' => $tenant->id,
            'channels' => ['telegram', 'email'],
            'status' => BroadcastStatus::Sending,
            'body' => 'Акция!',
        ]);

        $fresh = $this->deliver($broadcast);

        $this->assertCount(1, $this->telegram->sent);
        $this->assertSame('777', $this->telegram->sent[0]['chatId']);
        $this->assertSame('Акция!', $this->telegram->sent[0]['text']);
        Mail::assertQueued(BroadcastMail::class, 1);
        $this->assertSame(2, $fresh->sent_count);
        $this->assertSame(BroadcastStatus::Sent, $fresh->status);
        $this->assertNull($fresh->next_run_at);

        // Журнал доставки: по строке на получателя/канал.
        $this->assertDatabaseCount('broadcast_deliveries', 2);
        $this->assertDatabaseHas('broadcast_deliveries', ['broadcast_id' => $broadcast->id, 'channel' => 'telegram', 'status' => 'sent']);
        $this->assertDatabaseHas('broadcast_deliveries', ['broadcast_id' => $broadcast->id, 'channel' => 'email', 'status' => 'sent']);
    }

    public function test_delivers_only_to_selected_clients(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $chosen = $this->clientWithTelegram($tenant, '101');
        $this->clientWithTelegram($tenant, '202'); // не выбран

        $broadcast = Broadcast::factory()->create([
            'tenant_id' => $tenant->id,
            'channels' => ['telegram'],
            'client_ids' => [$chosen->id],
            'status' => BroadcastStatus::Sending,
        ]);

        $fresh = $this->deliver($broadcast);

        $this->assertCount(1, $this->telegram->sent);
        $this->assertSame('101', $this->telegram->sent[0]['chatId']);
        $this->assertSame(1, $fresh->sent_count);
    }

    public function test_unreachable_client_is_recorded_as_skipped(): void
    {
        $tenant = Tenant::factory()->max()->create();
        // Клиент с ником в карточке, но без диалога/чата — недостижим (как «Павел»).
        Client::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => null,
            'telegram_username' => 'pashkevi4',
            'marketing_opt_out' => false,
        ]);

        $broadcast = Broadcast::factory()->create([
            'tenant_id' => $tenant->id,
            'channels' => ['telegram'],
            'status' => BroadcastStatus::Sending,
        ]);

        $fresh = $this->deliver($broadcast);

        $this->assertSame(0, $fresh->sent_count);
        $this->assertSame(0, $fresh->failed_count);
        $this->assertDatabaseHas('broadcast_deliveries', [
            'broadcast_id' => $broadcast->id,
            'status' => 'skipped',
        ]);
    }

    public function test_failed_delivery_is_recorded_with_error(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $this->clientWithTelegram($tenant, '999');

        $broadcast = Broadcast::factory()->create([
            'tenant_id' => $tenant->id,
            'channels' => ['telegram'],
            'status' => BroadcastStatus::Sending,
        ]);

        $fresh = $this->deliver($broadcast, throws: true);

        $this->assertSame(0, $fresh->sent_count);
        $this->assertSame(1, $fresh->failed_count);
        $this->assertDatabaseHas('broadcast_deliveries', [
            'broadcast_id' => $broadcast->id,
            'channel' => 'telegram',
            'status' => 'failed',
            'error' => 'boom',
        ]);
    }

    public function test_skips_opted_out_clients(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $optedOut = $this->clientWithTelegram($tenant, '111');
        $optedOut->forceFill(['marketing_opt_out' => true])->save();

        $broadcast = Broadcast::factory()->create([
            'tenant_id' => $tenant->id,
            'channels' => ['telegram'],
            'status' => BroadcastStatus::Sending,
        ]);

        $fresh = $this->deliver($broadcast);

        $this->assertCount(0, $this->telegram->sent);
        $this->assertSame(0, $fresh->sent_count);
    }

    public function test_recurring_broadcast_reschedules_next_run(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $this->clientWithTelegram($tenant, '222');

        $broadcast = Broadcast::factory()->create([
            'tenant_id' => $tenant->id,
            'channels' => ['telegram'],
            'status' => BroadcastStatus::Sending,
            'recurrence' => BroadcastRecurrence::Weekly,
        ]);

        $fresh = $this->deliver($broadcast);

        $this->assertSame(BroadcastStatus::Scheduled, $fresh->status);
        $this->assertNotNull($fresh->next_run_at);
        $this->assertTrue($fresh->next_run_at->greaterThan(now()->addDays(6)));
    }

    public function test_does_not_deliver_without_broadcasts_feature(): void
    {
        // Тариф без права на рассылки — доставка отменяется (жёсткий рубеж).
        $tenant = Tenant::factory()->create(); // Trial: broadcasts=false
        $this->clientWithTelegram($tenant, '333');

        $broadcast = Broadcast::factory()->create([
            'tenant_id' => $tenant->id,
            'channels' => ['telegram'],
            'status' => BroadcastStatus::Sending,
        ]);

        $fresh = $this->deliver($broadcast);

        $this->assertCount(0, $this->telegram->sent);
        $this->assertSame(BroadcastStatus::Canceled, $fresh->status);
    }
}
