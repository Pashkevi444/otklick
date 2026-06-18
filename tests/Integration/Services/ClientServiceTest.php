<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Models\Channel;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Services\ClientService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientServiceTest extends TestCase
{
    use RefreshDatabase;

    private function conversation(Tenant $tenant, array $overrides = []): Conversation
    {
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);

        return Conversation::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'contact_phone' => '+79991112233',
            'contact_name' => 'Иван',
            'contact_ref' => 'https://t.me/ivan',
        ], $overrides));
    }

    public function test_links_conversation_to_new_client_and_backfills_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);

        $conversation = $this->conversation($tenant);
        $this->app->make(ClientService::class)->linkConversation($conversation);

        $conversation->refresh();
        $this->assertNotNull($conversation->client_id);

        $client = Client::query()->findOrFail($conversation->client_id);
        $this->assertSame('+79991112233', $client->phone);
        $this->assertSame('Иван', $client->name);
        $this->assertSame('ivan', $client->telegram_username);
        $this->assertSame('telegram', $client->first_channel_type);
    }

    public function test_dedupes_clients_by_phone_within_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);
        $service = $this->app->make(ClientService::class);

        $first = $this->conversation($tenant, ['contact_name' => 'Гость']); // имя-плейсхолдер
        $service->linkConversation($first);

        $second = $this->conversation($tenant, ['contact_name' => 'Иван', 'external_chat_id' => '777']);
        $service->linkConversation($second);

        // Один клиент на телефон; имя дозаполнилось из второго диалога.
        $this->assertSame(1, Client::query()->count());
        $client = Client::query()->firstOrFail();
        $this->assertSame('Иван', $client->name);

        $second->refresh();
        $this->assertSame($client->id, $second->client_id);
    }

    public function test_without_phone_no_client_is_created(): void
    {
        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);

        $conversation = $this->conversation($tenant, ['contact_phone' => null]);
        $this->app->make(ClientService::class)->linkConversation($conversation);

        $this->assertSame(0, Client::query()->count());
        $conversation->refresh();
        $this->assertNull($conversation->client_id);
    }
}
