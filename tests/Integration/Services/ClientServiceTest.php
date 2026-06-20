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

    private function service(): ClientService
    {
        return $this->app->make(ClientService::class);
    }

    private function conversation(Tenant $tenant, Channel $channel, string $chatId, ?string $ref = 'https://t.me/ivan'): Conversation
    {
        return Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'external_chat_id' => $chatId,
            'contact_ref' => $ref,
        ]);
    }

    /** Эмуляция конвейера ContactCapture: attach + запись захваченных контактов. */
    private function ingest(Conversation $conversation, ?string $phone, ?string $name): void
    {
        $service = $this->service();
        $service->attachClient($conversation);

        if ($phone !== null && $phone !== '') {
            $service->recordPhone($conversation, $phone);
        }
        if ($name !== null && $name !== '' && ! in_array($name, ['Гость', 'Гость сайта'], true)) {
            $service->recordName($conversation, $name);
        }
    }

    private function tenant(): Tenant
    {
        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);

        return $tenant;
    }

    public function test_creates_client_and_records_contacts(): void
    {
        $tenant = $this->tenant();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);

        $conversation = $this->conversation($tenant, $channel, 'tg-1');
        $this->ingest($conversation, '+79991112233', 'Иван');

        $conversation->refresh();
        $this->assertNotNull($conversation->client_id);

        $client = Client::query()->findOrFail($conversation->client_id);
        $this->assertSame('+79991112233', $client->phone);
        $this->assertSame('Иван', $client->name);
        $this->assertSame('ivan', $client->telegram_username);
        $this->assertSame('telegram', $client->first_channel_type);

        $identity = ClientIdentity::query()->firstOrFail();
        $this->assertSame('tg-1', $identity->identity);
        $this->assertSame($client->id, $identity->client_id);
    }

    public function test_creates_client_even_without_phone(): void
    {
        $tenant = $this->tenant();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);

        // Нормализация: каждый лид имеет карточку клиента, даже без телефона.
        $conversation = $this->conversation($tenant, $channel, 'tg-2', ref: null);
        $this->ingest($conversation, null, null);

        $this->assertSame(1, Client::query()->count());
        $conversation->refresh();
        $this->assertNotNull($conversation->client_id);
        $this->assertNull(Client::query()->findOrFail($conversation->client_id)->phone);
    }

    public function test_attach_matches_existing_client_by_telegram_username(): void
    {
        $tenant = $this->tenant();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]); // telegram

        // Карточка с ником, но без записанного chat_id (легаси/создана отдельно).
        $existing = Client::factory()->create([
            'tenant_id' => $tenant->id, 'name' => 'Павел', 'phone' => '+79237032792', 'telegram_username' => 'ivan',
        ]);

        $conv = $this->conversation($tenant, $channel, 'tg-new'); // contact_ref = t.me/ivan
        $this->service()->attachClient($conv);

        // Узнали существующую карточку по нику, дубль не создан.
        $this->assertSame(1, Client::query()->count());
        $this->assertSame($existing->id, $conv->fresh()->client_id);
        // Теперь у неё записан chat_id — дальше матчинг прямой по identity.
        $this->assertNotNull(ClientIdentity::query()->where('client_id', $existing->id)->where('identity', 'tg-new')->first());
    }

    public function test_merges_clients_when_phone_matches_another(): void
    {
        $tenant = $this->tenant();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);

        // Чат A: дал телефон → клиент X.
        $first = $this->conversation($tenant, $channel, 'chat-A');
        $this->ingest($first, '+79991112233', 'Иван');
        $clientX = $first->fresh()->client_id;
        $first->forceFill(['status' => ConversationStatus::Closed])->save();

        // Чат B того же телефона → создаётся пустой клиент Y, затем склейка в X.
        $second = $this->conversation($tenant, $channel, 'chat-B');
        $this->ingest($second, '+79991112233', 'Гость');

        $this->assertSame(1, Client::query()->count()); // склеились в одного
        $this->assertSame($clientX, $second->fresh()->client_id);
        // Обе нативные идентичности теперь у X.
        $this->assertSame(2, ClientIdentity::query()->where('client_id', $clientX)->count());
    }

    public function test_recognizes_returning_by_channel_identity(): void
    {
        $tenant = $this->tenant();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);

        $first = $this->conversation($tenant, $channel, 'tg-777');
        $this->ingest($first, '+79991112233', 'Иван');
        $clientId = $first->fresh()->client_id;
        $first->forceFill(['status' => ConversationStatus::Closed])->save();

        // Новый чистый диалог того же чата — узнаём по идентичности (без телефона).
        $second = Conversation::factory()->create([
            'tenant_id' => $tenant->id, 'channel_id' => $channel->id, 'external_chat_id' => 'tg-777', 'client_id' => null,
        ]);
        $this->service()->attachClient($second);

        $second->refresh();
        $this->assertSame($clientId, $second->client_id);
        // Контакты читаются из карточки (буфер лида не существует).
        $this->assertSame('Иван', $second->displayName());
        $this->assertSame('+79991112233', $second->displayPhone());
    }

    public function test_delete_forgets_client_then_new_contact_is_a_new_client(): void
    {
        $tenant = $this->tenant();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);

        $conv = $this->conversation($tenant, $channel, 'tg-9');
        $this->ingest($conv, '+79991112233', 'Пётр');
        $client = Client::query()->firstOrFail();
        $conv->forceFill(['status' => ConversationStatus::Closed])->save();

        $this->service()->delete($client);

        $this->assertSame(0, Client::query()->count());
        $this->assertSame(0, ClientIdentity::query()->count());
        $this->assertNull($conv->fresh()->client_id);

        // Тот же чат пишет снова — НЕ узнан, заводится НОВАЯ карточка (бот забыл).
        $fresh = Conversation::factory()->create([
            'tenant_id' => $tenant->id, 'channel_id' => $channel->id, 'external_chat_id' => 'tg-9', 'client_id' => null,
        ]);
        $this->service()->attachClient($fresh);

        $newClientId = $fresh->fresh()->client_id;
        $this->assertNotNull($newClientId);
        $this->assertNotSame($client->id, $newClientId);
        $this->assertNull(Client::query()->findOrFail($newClientId)->name);
    }
}
