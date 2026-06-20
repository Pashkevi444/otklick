<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Enums\ConversationStatus;
use App\Models\Channel;
use App\Models\Client;
use App\Models\ClientIdentity;
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

    public function test_link_records_channel_identity(): void
    {
        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);

        $conversation = $this->conversation($tenant, ['external_chat_id' => 'tg-123']);
        $this->app->make(ClientService::class)->linkConversation($conversation);

        $identity = ClientIdentity::query()->firstOrFail();
        $this->assertSame('telegram', $identity->channel_type->value);
        $this->assertSame('tg-123', $identity->identity);
        $this->assertSame($conversation->fresh()->client_id, $identity->client_id);
    }

    public function test_recognizes_returning_client_by_channel_identity(): void
    {
        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);
        $service = $this->app->make(ClientService::class);

        // Первый контакт: дал телефон → создан клиент + записана идентичность tg-777.
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $first = Conversation::factory()->create([
            'tenant_id' => $tenant->id, 'channel_id' => $channel->id, 'external_chat_id' => 'tg-777',
            'contact_phone' => '+79991112233', 'contact_name' => 'Иван', 'contact_ref' => 'https://t.me/ivan',
        ]);
        $service->linkConversation($first);
        $clientId = $first->fresh()->client_id;
        $first->forceFill(['status' => ConversationStatus::Closed])->save(); // закрыт → можно новый диалог чата

        // Новый чистый диалог того же чата — узнаём по идентичности, без телефона.
        $second = Conversation::factory()->create([
            'tenant_id' => $tenant->id, 'channel_id' => $channel->id, 'external_chat_id' => 'tg-777',
            'contact_phone' => null, 'contact_name' => null, 'client_id' => null,
        ]);
        $service->recognizeReturning($second);

        $second->refresh();
        $this->assertSame($clientId, $second->client_id);
        $this->assertSame('Иван', $second->contact_name);
        $this->assertSame('+79991112233', $second->contact_phone);
    }

    public function test_delete_forgets_client_and_clears_links(): void
    {
        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);
        $service = $this->app->make(ClientService::class);

        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $conv = Conversation::factory()->create([
            'tenant_id' => $tenant->id, 'channel_id' => $channel->id, 'external_chat_id' => 'tg-9',
            'contact_phone' => '+79990001122', 'contact_name' => 'Пётр',
        ]);
        $service->linkConversation($conv);
        $client = Client::query()->firstOrFail();
        $conv->forceFill(['status' => ConversationStatus::Closed])->save(); // закрыт → можно новый диалог чата

        $service->delete($client);

        // Клиент и его идентичности удалены, лид отвязан (история лида осталась).
        $this->assertSame(0, Client::query()->count());
        $this->assertSame(0, ClientIdentity::query()->count());
        $this->assertNull($conv->fresh()->client_id);

        // Новый диалог того же чата — НЕ узнаётся (бот забыл).
        $fresh = Conversation::factory()->create([
            'tenant_id' => $tenant->id, 'channel_id' => $channel->id, 'external_chat_id' => 'tg-9',
            'contact_phone' => null, 'client_id' => null,
        ]);
        $service->recognizeReturning($fresh);
        $this->assertNull($fresh->fresh()->client_id);
    }
}
