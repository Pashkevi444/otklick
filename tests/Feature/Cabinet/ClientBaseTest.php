<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Client;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ClientBaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_base_requires_plan_feature(): void
    {
        // Тариф без права clientBase (пробный) — доступа нет.
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->get('/cabinet/clients')->assertForbidden();
    }

    public function test_index_lists_and_filters_clients(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();

        Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Анна', 'first_channel_type' => 'telegram']);
        Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Борис', 'first_channel_type' => 'vk']);

        $this->actingAs($owner)
            ->get('/cabinet/clients')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/Clients/Index')
                ->has('clients', 2)
                ->has('channels'));

        // Фильтр по каналу.
        $this->actingAs($owner)
            ->get('/cabinet/clients?channel=vk')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('clients', 1)->where('clients.0.name', 'Борис'));
    }

    public function test_show_displays_client_and_conversations(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Анна']);
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        Conversation::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id, 'client_id' => $client->id]);

        $this->actingAs($owner)
            ->get("/cabinet/clients/{$client->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/Clients/Show')
                ->where('client.name', 'Анна')
                ->has('conversations', 1));
    }

    public function test_update_edits_client_fields(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)
            ->put("/cabinet/clients/{$client->id}", ['name' => 'Новое имя', 'email' => 'a@b.ru', 'notes' => 'VIP'])
            ->assertRedirect();

        $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Новое имя', 'email' => 'a@b.ru', 'notes' => 'VIP']);
    }

    public function test_refresh_summary_generates_from_dialog(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'summary' => null]);
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $conversation = Conversation::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id, 'client_id' => $client->id]);
        Message::factory()->create(['tenant_id' => $tenant->id, 'conversation_id' => $conversation->id, 'direction' => MessageDirection::Inbound, 'text' => 'Хочу записаться на стрижку']);
        Message::factory()->create(['tenant_id' => $tenant->id, 'conversation_id' => $conversation->id, 'direction' => MessageDirection::Outbound, 'text' => 'Записал вас на завтра в 12:00']);

        $this->actingAs($owner)
            ->post("/cabinet/clients/{$client->id}/summary")
            ->assertRedirect();

        $client->refresh();
        $this->assertNotNull($client->summary);
        $this->assertNotNull($client->summary_generated_at);
    }

    public function test_deleting_client_from_detail_redirects_to_grid(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)
            ->from("/cabinet/clients/{$client->id}")
            ->delete("/cabinet/clients/{$client->id}")
            ->assertRedirect('/cabinet/clients');

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    public function test_destroy_deletes_client(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)
            ->from('/cabinet/clients')
            ->delete("/cabinet/clients/{$client->id}")
            ->assertRedirect('/cabinet/clients'); // из грида — назад в грид

        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    public function test_new_client_highlighted_until_card_opened(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();
        $client = Client::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Новенький']);

        $notification = UserNotification::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'type' => 'new_client',
            'entity_type' => 'client',
            'entity_id' => $client->id,
            'title' => 'Новый клиент',
        ]);

        // Заход в список: клиент подсвечен «Новый», но метку НЕ гасим (как уведомления).
        $this->actingAs($owner)
            ->get('/cabinet/clients')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('newClientIds', [$client->id]));
        $this->assertNull($notification->fresh()->read_at);

        // Открыли карточку клиента → метка гаснет (его уже видели).
        $this->actingAs($owner)->get("/cabinet/clients/{$client->id}")->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);

        // Повторный заход в список — клиент больше не «Новый».
        $this->actingAs($owner)
            ->get('/cabinet/clients')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('newClientIds', []));
    }

    public function test_read_all_clears_every_new_client_highlight(): void
    {
        $tenant = Tenant::factory()->max()->create();
        $owner = User::factory()->owner($tenant)->create();
        $a = Client::factory()->create(['tenant_id' => $tenant->id]);
        $b = Client::factory()->create(['tenant_id' => $tenant->id]);

        foreach ([$a, $b] as $client) {
            UserNotification::create([
                'tenant_id' => $tenant->id,
                'user_id' => $owner->id,
                'type' => 'new_client',
                'entity_type' => 'client',
                'entity_id' => $client->id,
                'title' => 'Новый клиент',
            ]);
        }

        // Кнопка «Прочитать всё» гасит подсветку у всех клиентов разом.
        $this->actingAs($owner)
            ->from('/cabinet/clients')
            ->post('/cabinet/clients/read-all')
            ->assertRedirect('/cabinet/clients');

        $this->actingAs($owner)
            ->get('/cabinet/clients')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('newClientIds', []));
    }
}
